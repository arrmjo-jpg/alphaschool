<?php

namespace App\Core\Concerns;

use App\Core\ValueObjects\DateRange;
use App\Core\ValueObjects\ReasonCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The shared temporal/effective-dating pattern
 * (docs/DOMAIN_BLUEPRINT.md §6, "Assignment Pattern" / Addendum A3, B1).
 *
 * Adopting models represent a fact scoped to a date range that must never
 * be silently overwritten to represent a different period -- a change
 * closes the current row (closeAssignment) and a new row is created for
 * the next period, chained via whatever FK convention the consuming
 * module uses (e.g. previous_enrollment_id) -- this trait does not manage
 * that chaining itself, only the temporal/overlap mechanics of one row.
 *
 * Interval semantics are HALF-OPEN -- see App\Core\ValueObjects\DateRange.
 *
 * Required columns on the consuming table: effective_from (date),
 * effective_until (date, nullable), status (string: scheduled|active|
 * ended|cancelled), reason_code_id (nullable FK to reason_codes).
 *
 * Consuming models MUST implement:
 *  - temporalScopeAttributes(): which other rows compete for exclusivity
 *    (e.g. ['section_id' => $this->section_id] -- two teachers cannot
 *    both be the active homeroom teacher of the same section at once).
 *  - temporalReasonContext(): the reason_codes.context this model's
 *    reasons are looked up under (e.g. 'homeroom_teacher_assignment').
 *
 * CONCURRENCY SAFETY (High-Priority Core Architecture Backlog, fixed
 * here rather than per-consumer -- GuardianStudent was the first real
 * consumer and had carried this as a documented, deliberately
 * unpatched-locally limitation until now): save() is overridden to wrap
 * the whole operation -- competitor lookup, overlap check, and the
 * actual write -- in one DB::transaction(), and guardAgainstOverlap()'s
 * competitor query holds a real row lock (lockForUpdate()) scoped to
 * temporalScopeAttributes() for its duration. This closes the race
 * where two concurrent saves for the SAME EXISTING scope could both
 * pass the overlap check before either write lands.
 *
 * KNOWN REMAINING LIMITATION, disclosed not silently accepted: row
 * locking cannot protect a scope that has zero prior rows -- two
 * concurrent *first-ever* creates for a brand-new scope (nothing yet
 * exists to lock) could still both pass the check. This is not
 * exploitable by a caller that already locks a real, always-existing
 * anchor row first (e.g. the Enrollment or Section row) before creating
 * the temporal assignment -- exactly the discipline every Action in
 * this codebase already follows (ConvertApplicantToStudentAction locks
 * Applicant; the Sprint 4.4 Enrollment transition Actions lock
 * Enrollment) -- but a caller that does not is not protected by this
 * trait alone. A DB-level exclusion constraint would close this fully;
 * not built here, since no consumer has forced the question yet
 * (promotion, not prediction).
 *
 * DATE-BOUNDARY NORMALIZATION: setEffectiveFromAttribute()/
 * setEffectiveUntilAttribute() below normalize both to
 * Carbon::parse($value)->startOfDay() on every write, centrally --
 * previously each consumer needed its own copy of this (GuardianStudent
 * carried a local stopgap, now removed). A plain 'date' cast alone does
 * NOT strip time-of-day from the stored value, only from what Eloquent
 * displays back through the accessor -- left unhandled, a row created
 * with effective_from = now() (a real timestamp, not midnight) would
 * compare as "starting after" today at midnight in scopeAsOf(), and be
 * wrongly excluded from active()/asOf(today()) for the rest of its own
 * creation day.
 *
 * See docs/developer/temporal-pattern.md for a worked example.
 */
trait HasTemporalAssignment
{
    abstract public function temporalScopeAttributes(): array;

    abstract public function temporalReasonContext(): string;

    protected static function bootHasTemporalAssignment(): void
    {
        static::saving(function (Model $model) {
            $model->guardAgainstOverlap();
        });
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function setEffectiveFromAttribute(mixed $value): void
    {
        $this->attributes['effective_from'] = $value !== null ? Carbon::parse($value)->startOfDay() : null;
    }

    public function setEffectiveUntilAttribute(mixed $value): void
    {
        $this->attributes['effective_until'] = $value !== null ? Carbon::parse($value)->startOfDay() : null;
    }

    public function range(): DateRange
    {
        return new DateRange($this->effective_from, $this->effective_until);
    }

    /**
     * Records whose date range covers $date (defaults to now), excluding
     * cancelled records. `status` is treated as an administrative label,
     * never authoritative for "is this actually in effect" -- that is
     * always derived from the date range, so nothing needs a scheduled
     * job to flip scheduled -> active at the right moment.
     */
    public function scopeAsOf(Builder $query, Carbon|string $date): Builder
    {
        $date = Carbon::parse($date)->startOfDay();

        return $query->where('status', '!=', 'cancelled')
            ->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('effective_until')->orWhere('effective_until', '>', $date);
            });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->asOf(now());
    }

    protected function guardAgainstOverlap(): void
    {
        if ($this->status === 'cancelled') {
            return; // cancelled records never competed for exclusivity
        }

        $relevantColumns = array_merge(
            ['effective_from', 'effective_until', 'status'],
            array_keys($this->temporalScopeAttributes()),
        );

        if ($this->exists && ! $this->isDirty($relevantColumns)) {
            return;
        }

        $thisRange = $this->range();

        // lockForUpdate() -- held for the remainder of this transaction
        // (opened by the save() override above), through the actual
        // write. Genuinely serializes two concurrent saves competing for
        // the same scope where at least one competitor row already
        // exists; see this trait's own docblock for the narrower,
        // disclosed limitation when zero prior rows exist yet.
        $competitors = static::query()
            ->where($this->temporalScopeAttributes())
            ->where('status', '!=', 'cancelled')
            ->when($this->exists, fn (Builder $q) => $q->where($this->getKeyName(), '!=', $this->getKey()))
            ->lockForUpdate()
            ->get();

        foreach ($competitors as $competitor) {
            if ($thisRange->overlaps($competitor->range())) {
                throw new RuntimeException(sprintf(
                    '%s: overlapping temporal record for the same scope %s -- conflicts with #%s %s.',
                    static::class,
                    json_encode($this->temporalScopeAttributes()),
                    $competitor->getKey(),
                    $competitor->range(),
                ));
            }
        }
    }

    /**
     * Ends this record as of $effectiveUntil (defaults to today), with a
     * reason drawn from this model's reason context. Does not create a
     * replacement record -- opening the next period, if any, is the
     * calling module's responsibility (it knows what "the next period"
     * means for its own domain).
     */
    public function closeAssignment(ReasonCode $reason, Carbon|string|null $effectiveUntil = null, ?Model $endedBy = null): static
    {
        $this->assertReasonIsValidForContext($reason);

        $this->effective_until = $effectiveUntil ? Carbon::parse($effectiveUntil)->startOfDay() : now()->startOfDay();
        $this->status = 'ended';
        $this->reason_code_id = $this->resolveReasonCodeId($reason);

        if ($endedBy !== null) {
            $this->ended_by_id = $endedBy->getKey();
        }

        $this->save();

        return $this;
    }

    /**
     * Marks this record as never having been valid in the first place --
     * distinct from closeAssignment(), which represents a real, legitimate
     * conclusion. Use this for correcting a mistaken assignment, not for
     * an ordinary end-of-period transition.
     */
    public function cancelAssignment(ReasonCode $reason, ?Model $endedBy = null): static
    {
        $this->assertReasonIsValidForContext($reason);

        $this->status = 'cancelled';
        $this->reason_code_id = $this->resolveReasonCodeId($reason);

        if ($endedBy !== null) {
            $this->ended_by_id = $endedBy->getKey();
        }

        $this->save();

        return $this;
    }

    protected function assertReasonIsValidForContext(ReasonCode $reason): void
    {
        $this->resolveReasonCodeId($reason);
    }

    protected function resolveReasonCodeId(ReasonCode $reason): int
    {
        $reasonModel = \App\Core\Models\ReasonCode::query()
            ->forContext($this->temporalReasonContext())
            ->where('code', $reason->code)
            ->first();

        if ($reasonModel === null) {
            throw new RuntimeException(sprintf(
                "'%s' is not a registered, active reason code for context '%s'.",
                $reason->code,
                $this->temporalReasonContext(),
            ));
        }

        return $reasonModel->getKey();
    }
}
