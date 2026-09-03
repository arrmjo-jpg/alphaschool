<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Core\ValueObjects\ReassignmentImpact;
use App\Modules\People\Events\StudentGraduated;
use App\Modules\People\Events\StudentReactivated;
use App\Modules\People\Events\StudentWithdrawn;
use App\Modules\People\Exceptions\StudentNotActiveException;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The permanent identity anchor for "this Person has ever been enrolled
 * here" (docs/DOMAIN_BLUEPRINT.md §3, ADR-0004). Owns ONLY a coarse
 * lifecycle status -- academic year, grade, branch, and section belong
 * to Enrollment (Sprint 4.3), never to Student directly. A withdrawn or
 * graduated student's later re-admission reuses this same Student row
 * via reactivate() below; Student is never recreated.
 *
 * Never branch-scoped (Addendum B6): branch relevance arrives entirely
 * through Enrollment, never a column here. `current_enrollment_id`
 * added Sprint 4.3, alongside Enrollment itself. No `student_number`
 * yet -- numbering is explicitly out of scope pending the still-open
 * numbering-scheme decision.
 */
class Student extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    /**
     * @use HasFactory<StudentFactory>
     */
    use HasFactory;

    use HasPublicId;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRADUATED = 'graduated';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = ['person_id', 'lifecycle_status', 'current_enrollment_id'];

    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function currentEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'current_enrollment_id');
    }

    /**
     * The Return-case reactivation (Sprint 4.3 Technical Specification
     * §6/§11) -- transitions from STATUS_WITHDRAWN or STATUS_GRADUATED
     * back to STATUS_ACTIVE. No-op if already active, matching this
     * Phase's established idempotency convention. withdraw()/graduate()
     * below are Sprint 4.4's own additions -- unlike reactivate()'s
     * no-op, both throw on an already-non-active Student, matching the
     * terminal-state discipline Sprint 4.4's Architecture Pass
     * explicitly requires for Enrollment's own equivalent transitions.
     */
    public function reactivate(): void
    {
        if ($this->lifecycle_status === self::STATUS_ACTIVE) {
            return;
        }

        $this->lifecycle_status = self::STATUS_ACTIVE;
        $this->save();

        // DB::afterCommit(), matching StudentEnrolled's own dispatch
        // discipline (Sprint 4.3 Technical Specification §10) -- not a
        // no-op if called outside a transaction, Laravel runs the
        // callback immediately in that case.
        DB::afterCommit(function (): void {
            StudentReactivated::dispatch($this);
        });
    }

    /**
     * Sprint 4.4 -- called by Academic's WithdrawEnrollmentAction
     * alongside the current Enrollment's own withdraw(). Throws rather
     * than no-ops (StudentNotActiveException) -- in practice this is
     * always already prevented by the calling Action's prior
     * EnrollmentNotActiveException check, since Student and its current
     * Enrollment transition together; this guard exists so Student's
     * own contract doesn't solely rely on every future caller getting
     * that ordering right.
     */
    public function withdraw(): void
    {
        if ($this->lifecycle_status !== self::STATUS_ACTIVE) {
            throw new StudentNotActiveException($this, 'withdraw');
        }

        $this->lifecycle_status = self::STATUS_WITHDRAWN;
        $this->save();

        DB::afterCommit(function (): void {
            StudentWithdrawn::dispatch($this);
        });
    }

    /**
     * Mirrors withdraw() exactly, called by GraduateEnrollmentAction.
     */
    public function graduate(): void
    {
        if ($this->lifecycle_status !== self::STATUS_ACTIVE) {
            throw new StudentNotActiveException($this, 'graduate');
        }

        $this->lifecycle_status = self::STATUS_GRADUATED;
        $this->save();

        DB::afterCommit(function (): void {
            StudentGraduated::dispatch($this);
        });
    }

    /**
     * Trivial per the Sprint 2.4 execution plan and the architectural
     * clarification agreed for Employee: Student holds only its own
     * person_id at this point. It is still called at most once per
     * successful merge (ADR-0009 -- Identity Maintenance guarantees
     * this, Student does not defend against it itself), but structural
     * validity is now self-checked here via $dryRun (Sprint 3.2),
     * rather than assumed pre-validated by an external caller.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId, bool $dryRun = false): void
    {
        if ($dryRun) {
            if (static::where('person_id', $newPersonId)->exists()) {
                throw new RuntimeException(
                    "Student: person #{$newPersonId} already holds a Student row -- reassigning person #{$oldPersonId} would violate the unique person_id constraint."
                );
            }

            return;
        }

        static::where('person_id', $oldPersonId)->update(['person_id' => $newPersonId]);
    }

    /**
     * @return ReassignmentImpact[]
     */
    public function previewReassignment(int $oldPersonId, int $newPersonId): array
    {
        $ids = static::where('person_id', $oldPersonId)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        return [new ReassignmentImpact(static::class, 'person_id', $ids, 'The Student row would move.')];
    }

    /**
     * A deliberate no-op: Student holds no personally-identifying field
     * of its own (lifecycle_status is not PII) -- there is nothing here
     * for Person's future anonymization (Phase 3) to redact.
     */
    public function anonymizePerson(int $personId): void
    {
        //
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['lifecycle_status', 'current_enrollment_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
