<?php

namespace App\Modules\IdentityMaintenance\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Core\Services\DuplicateDetectionService;
use App\Core\ValueObjects\ReassignmentImpact;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Person;
use Database\Factories\DuplicateFlagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A persisted, human-reviewable output of
 * App\Core\Services\DuplicateDetectionService's scoring (Addendum C2:
 * Detection stays a generic, stateless Core algorithm; Resolution --
 * this table -- is Identity Maintenance's, since it has real domain
 * consequences). `source_person_id`/`candidate_person_id` mirror that
 * service's own probe/candidate vocabulary rather than a
 * primary/duplicate label that would presuppose an outcome before any
 * review has happened.
 *
 * `status` is a triage classification only -- `merge_candidate` does
 * not create or link to a MergeRequest (that aggregate, and Merge
 * execution itself, is Sprint 3.2 scope, deliberately not built here).
 *
 * SoftDeletes, not hard delete: this is evidentiary, the same treatment
 * already given to Sprint 1.2's ApprovalRequest ("approval decisions
 * are evidentiary and nothing should allow that trail to silently
 * disappear").
 *
 * Implements both Identity Maintenance contracts itself -- this table
 * directly references two People, so a Person merge affecting either
 * side must be reassignable. Not a plain, unconditional move (Sprint
 * 3.2 found and fixed a self-reference edge case -- see
 * reassignPerson()'s own docblock).
 */
class DuplicateFlag extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_MERGE_CANDIDATE = 'merge_candidate';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * Sprint 3.2: the terminal state for the flag that originated a
     * successful merge, distinct from merge_candidate (flagged, not yet
     * executed). Set by MergeOrchestrationService::execute() on the
     * originating flag only -- other flags referencing either merged
     * Person are handled generically by reassignPerson() below, not by
     * this status.
     */
    public const STATUS_MERGED = 'merged';

    protected $fillable = [
        'source_person_id',
        'candidate_person_id',
        'score',
        'tier',
        'status',
        'resolved_by_id',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): DuplicateFlagFactory
    {
        return DuplicateFlagFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $flag): void {
            if ($flag->source_person_id === $flag->candidate_person_id) {
                throw new InvalidArgumentException(
                    'DuplicateFlag: a person cannot be flagged as a duplicate of themselves.'
                );
            }

            if ($flag->isDirty('tier') && ! in_array($flag->tier, [
                DuplicateDetectionService::TIER_LIKELY,
                DuplicateDetectionService::TIER_CERTAIN,
            ], true)) {
                throw new InvalidArgumentException(
                    "DuplicateFlag: tier '{$flag->tier}' is not flaggable -- only ".
                    DuplicateDetectionService::TIER_LIKELY.'/'.DuplicateDetectionService::TIER_CERTAIN.' results are persisted.'
                );
            }
        });
    }

    public function sourcePerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'source_person_id');
    }

    public function candidatePerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'candidate_person_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    /**
     * Self-reference exclusion (Sprint 3.2): if this flag is the one
     * that originated the merge (structurally: source=losing,
     * candidate=winning, or vice versa), a naive reassignment would
     * collapse both columns onto the winning Person, violating this
     * model's own self-reference guard above. Such a row is excluded
     * from the update rather than attempted and failed -- the
     * comparison it represented is moot now that both sides are the
     * same Person. MergeOrchestrationService::execute() handles the
     * originating flag's own lifecycle separately (STATUS_MERGED)
     * before this runs. $dryRun has nothing to reject: the exclusion
     * makes real execution self-correcting.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId, bool $dryRun = false): void
    {
        if ($dryRun) {
            return;
        }

        static::where('source_person_id', $oldPersonId)
            ->where('candidate_person_id', '!=', $newPersonId)
            ->update(['source_person_id' => $newPersonId]);

        static::where('candidate_person_id', $oldPersonId)
            ->where('source_person_id', '!=', $newPersonId)
            ->update(['candidate_person_id' => $newPersonId]);
    }

    /**
     * @return ReassignmentImpact[]
     */
    public function previewReassignment(int $oldPersonId, int $newPersonId): array
    {
        $impacts = [];

        $asSource = static::where('source_person_id', $oldPersonId)->pluck('id')->all();
        if ($asSource !== []) {
            $impacts[] = new ReassignmentImpact(static::class, 'source_person_id', $asSource, count($asSource).' flag(s) would move (as source_person_id).');
        }

        $asCandidate = static::where('candidate_person_id', $oldPersonId)->pluck('id')->all();
        if ($asCandidate !== []) {
            $impacts[] = new ReassignmentImpact(static::class, 'candidate_person_id', $asCandidate, count($asCandidate).' flag(s) would move (as candidate_person_id).');
        }

        return $impacts;
    }

    /**
     * A deliberate no-op: this row holds a score, a tier, and free-text
     * resolution notes -- no field that directly identifies the Person
     * beyond the references themselves, which anonymization does not
     * remove (the fact that a comparison happened is not itself PII).
     */
    public function anonymizePerson(int $personId): void
    {
        // Intentionally empty -- see docblock above.
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'resolved_by_id', 'resolved_at', 'resolution_notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
