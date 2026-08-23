<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SectionAssignment;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lives in Academic (Domain), not People (ADR-0026, Phase 5 Layering
 * Review) -- reads Section (Academic's own Master Data) and the closed-
 * year guard, both Academic-owned decisions; Enrollment/SectionAssignment
 * only ever receive plain IDs from here, never cross the layering rule.
 *
 * Locks Enrollment, not Section -- Enrollment is SectionAssignment's own
 * temporalScopeAttributes() anchor (one active Section per Enrollment at
 * a time), so it's the row whose concurrent modification actually needs
 * serializing, matching every other Action in this codebase locking its
 * own natural anchor row first (ConvertApplicantToStudentAction locks
 * Applicant; the Sprint 4.4 Enrollment transition Actions lock
 * Enrollment).
 *
 * $effectiveFrom (docs/ADMIN_DESIGN_SYSTEM.md §31.11): this Action
 * predates UI Sprint 2 (Phase 5 Sprint B) and carried the exact same
 * hardcoded-now()/hardcoded-'active' defect UI Sprint 2's own
 * independent review found and fixed in AssignHomeroomTeacherAction --
 * closed here, before this slice's own Create form (which makes
 * effective_from a real, user-editable field) goes live, not after.
 */
class AssignSectionAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(Enrollment $enrollment, Section $section, Carbon|string|null $effectiveFrom = null): SectionAssignment
    {
        return DB::transaction(function () use ($enrollment, $section, $effectiveFrom): SectionAssignment {
            $lockedEnrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            // A withdrawn/graduated Enrollment should never receive a
            // new Section assignment -- reuses Sprint 4.4's own
            // exception rather than declaring a near-duplicate.
            if ($lockedEnrollment->status !== Enrollment::STATUS_ACTIVE) {
                throw new EnrollmentNotActiveException($lockedEnrollment, 'assign a section to');
            }

            // The target Section's own Academic Year, not the
            // Enrollment's -- SectionAssignment's own Consistency
            // Invariant (enforced in the model itself) already requires
            // both to agree, so this is equivalent either way; checked
            // here to match every other "new commitment" Action's own
            // convention of asserting on the entity actually being
            // written into.
            $this->academicCatalogService->assertAcademicYearIsOpen($section->academic_year_id);

            // Resolved once here, not left to the model's own
            // setEffectiveFromAttribute() mutator, because `status` must
            // agree with it -- a future-dated assignment is not yet in
            // effect, and 'active' would misrepresent that until the
            // date arrives (status stays administrative-only; asOf()/
            // active() never trust it either way, this only keeps the
            // label from contradicting what asOf() will itself compute).
            $resolvedEffectiveFrom = Carbon::parse($effectiveFrom ?? now())->startOfDay();
            $status = $resolvedEffectiveFrom->isAfter(Carbon::today()) ? 'scheduled' : 'active';

            // SectionAssignment's own Consistency Invariant
            // (booted()::saving()) throws if $enrollment and $section
            // don't agree on branch/academic_year/grade_level --
            // guardAgainstOverlap() (HasTemporalAssignment) throws if
            // this Enrollment already has an active SectionAssignment.
            // Neither is re-checked here; both are the model's own job.
            return SectionAssignment::create([
                'enrollment_id' => $lockedEnrollment->id,
                'section_id' => $section->id,
                'effective_from' => $resolvedEffectiveFrom,
                'status' => $status,
            ]);
        });
    }
}
