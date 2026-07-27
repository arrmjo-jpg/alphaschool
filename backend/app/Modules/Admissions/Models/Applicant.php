<?php

namespace App\Modules\Admissions\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Core\Models\ReasonCode;
use App\Core\ValueObjects\ReassignmentImpact;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Admissions\Events\ApplicantAccepted;
use App\Modules\Admissions\Events\ApplicantMovedToReview;
use App\Modules\Admissions\Events\ApplicantRejected;
use App\Modules\Admissions\Events\ApplicantTested;
use App\Modules\Admissions\Events\ApplicantWithdrawn;
use App\Modules\Admissions\Exceptions\InvalidRejectionReasonException;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Person;
use Database\Factories\ApplicantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The pre-enrollment funnel's own aggregate (Sprint 4.2 Technical
 * Specification) -- a separate identity from Student, by prior design
 * decision (frozen docs/DOMAIN_BLUEPRINT.md): an Applicant who is
 * rejected or withdraws never becomes a Student record at all. Its own
 * status machine here is deliberately narrower than frozen law's full
 * lifecycle (submitted -> ... -> converted/withdrawn/expired) -- see
 * the migration's own comment.
 *
 * Implements ReassignsIdentityReferences/RedactsPersonalData directly,
 * mirroring Student's own precedent exactly -- both are independent
 * context aggregates referencing Person by ID (docs/DOMAIN_BLUEPRINT.md
 * Addendum C11), not a child entity owned by another aggregate's
 * cascade (OwnedByAggregate would be the wrong contract here). Caught
 * by tests/Architecture/IdentityMaintenanceSchemaDeclarationTest.php,
 * not decided speculatively.
 */
class Applicant extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_TESTED = 'tested';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    private const TERMINAL_STATUSES = [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN];

    /**
     * The ReasonCode context reject() requires -- also referenced by
     * ReasonCodeSeeder, so the two never drift apart into two
     * independently-maintained copies of the same string.
     */
    public const REJECTION_REASON_CONTEXT = 'application_rejection';

    protected $fillable = [
        'person_id', 'submitted_by_guardian_id', 'branch_id',
        'academic_year_id', 'applied_for_grade_level_id',
        'application_number', 'status', 'rejection_reason_code_id',
    ];

    protected static function newFactory(): ApplicantFactory
    {
        return ApplicantFactory::new();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'submitted_by_guardian_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Academic's own model, referenced read-only -- this Applicant
     * never modifies it, matching the boundary already established for
     * Students in Sprint 4.1.
     */
    public function appliedForGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'applied_for_grade_level_id');
    }

    public function rejectionReasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'rejection_reason_code_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(AdmissionAssessment::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Unlike Student (unique person_id), Applicant's own uniqueness is
     * scoped to (person_id, academic_year_id) -- a child may legitimately
     * hold more than one Applicant row across different academic years.
     * The dry-run check below reflects that composite constraint, not
     * Student's simpler "already holds one" check.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId, bool $dryRun = false): void
    {
        $applicants = static::where('person_id', $oldPersonId)->get();

        if ($dryRun) {
            foreach ($applicants as $applicant) {
                if (static::where('person_id', $newPersonId)->where('academic_year_id', $applicant->academic_year_id)->exists()) {
                    throw new RuntimeException(
                        "Applicant: person #{$newPersonId} already holds an Applicant row for academic year #{$applicant->academic_year_id} -- reassigning person #{$oldPersonId} would violate the unique (person_id, academic_year_id) constraint."
                    );
                }
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

        return [new ReassignmentImpact(static::class, 'person_id', $ids, 'The Applicant row would move.')];
    }

    /**
     * A deliberate no-op, matching Student's own precedent exactly:
     * Applicant holds no personally-identifying field of its own (every
     * column here is an FK, a status, or an application number) -- there
     * is nothing for Person's future anonymization (Phase 3) to redact.
     */
    public function anonymizePerson(int $personId): void
    {
        //
    }

    /**
     * Idempotent on a repeat call from the same status -- matches
     * AcademicYear::close()'s own precedent (Sprint 4.1).
     */
    public function moveToReview(): void
    {
        if ($this->status !== self::STATUS_SUBMITTED) {
            return;
        }

        $this->status = self::STATUS_UNDER_REVIEW;
        $this->save();

        ApplicantMovedToReview::dispatch($this);
    }

    public function markTested(): void
    {
        if ($this->status !== self::STATUS_UNDER_REVIEW) {
            return;
        }

        $this->status = self::STATUS_TESTED;
        $this->save();

        ApplicantTested::dispatch($this);
    }

    public function accept(): void
    {
        if ($this->isTerminal()) {
            return;
        }

        $this->status = self::STATUS_ACCEPTED;
        $this->save();

        ApplicantAccepted::dispatch($this);
    }

    /**
     * Independent Review fix: previously accepted any reason_codes.id
     * satisfying the foreign key, with no check it actually belongs to
     * this Applicant's own rejection context -- confirmed here, not
     * just at the database FK layer.
     *
     * @throws InvalidRejectionReasonException
     */
    public function reject(int $reasonCodeId): void
    {
        if ($this->isTerminal()) {
            return;
        }

        $isValidReason = ReasonCode::query()
            ->where('id', $reasonCodeId)
            ->where('context', self::REJECTION_REASON_CONTEXT)
            ->where('is_active', true)
            ->exists();

        if (! $isValidReason) {
            throw new InvalidRejectionReasonException($reasonCodeId);
        }

        $this->status = self::STATUS_REJECTED;
        $this->rejection_reason_code_id = $reasonCodeId;
        $this->save();

        ApplicantRejected::dispatch($this);
    }

    public function withdraw(): void
    {
        if ($this->isTerminal()) {
            return;
        }

        $this->status = self::STATUS_WITHDRAWN;
        $this->save();

        ApplicantWithdrawn::dispatch($this);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'rejection_reason_code_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
