<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Core\ValueObjects\ReassignmentImpact;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The permanent identity anchor for "this Person has ever been enrolled
 * here" (docs/DOMAIN_BLUEPRINT.md §3, ADR-0004). Owns ONLY a coarse
 * lifecycle status -- academic year, grade, branch, and section belong
 * to Enrollment (Phase 4), never to Student directly. A withdrawn
 * student's later re-admission opens a new Enrollment period against
 * this same Student row; Student is never recreated.
 *
 * Never branch-scoped (Addendum B6): branch relevance arrives entirely
 * through Enrollment, never a column here. No `current_enrollment_id`
 * yet -- it would reference a table that doesn't exist until Phase 4.
 * No `student_number` yet -- numbering is explicitly out of this
 * sprint's scope pending the still-open numbering-scheme decision.
 */
class Student extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRADUATED = 'graduated';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = ['person_id', 'lifecycle_status'];

    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
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
            ->logOnly(['lifecycle_status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
