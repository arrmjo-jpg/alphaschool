<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SectionAssignment;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;
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
 */
class AssignSectionAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(Enrollment $enrollment, Section $section): SectionAssignment
    {
        return DB::transaction(function () use ($enrollment, $section): SectionAssignment {
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

            // SectionAssignment's own Consistency Invariant
            // (booted()::saving()) throws if $enrollment and $section
            // don't agree on branch/academic_year/grade_level --
            // guardAgainstOverlap() (HasTemporalAssignment) throws if
            // this Enrollment already has an active SectionAssignment.
            // Neither is re-checked here; both are the model's own job.
            return SectionAssignment::create([
                'enrollment_id' => $lockedEnrollment->id,
                'section_id' => $section->id,
                'effective_from' => now(),
                'status' => 'active',
            ]);
        });
    }
}
