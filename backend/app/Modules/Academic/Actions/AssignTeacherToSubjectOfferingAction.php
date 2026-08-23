<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Locks SubjectOffering, not Employee -- SubjectOffering is
 * TeacherAssignment's own temporalScopeAttributes() anchor (one active
 * teacher per SubjectOffering at a time), matching
 * AssignHomeroomTeacherAction's own lock-the-real-anchor-row discipline.
 *
 * $effectiveFrom (docs/ADMIN_DESIGN_SYSTEM.md §32.10): this Action
 * carried the exact same hardcoded-now()/hardcoded-'active' defect
 * already found and fixed twice in its sibling Actions
 * (AssignHomeroomTeacherAction, AssignSectionAction) -- closed here,
 * before this slice's own Create form (which makes effective_from a
 * real, user-editable field) goes live.
 */
class AssignTeacherToSubjectOfferingAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(SubjectOffering $subjectOffering, Employee $employee, Carbon|string|null $effectiveFrom = null): TeacherAssignment
    {
        return DB::transaction(function () use ($subjectOffering, $employee, $effectiveFrom): TeacherAssignment {
            $lockedOffering = SubjectOffering::query()->whereKey($subjectOffering->id)->lockForUpdate()->firstOrFail();

            $this->academicCatalogService->assertAcademicYearIsOpen($lockedOffering->academic_year_id);

            // Resolved once here, not left to the model's own
            // setEffectiveFromAttribute() mutator, because `status` must
            // agree with it -- a future-dated assignment is not yet in
            // effect, and 'active' would misrepresent that until the
            // date arrives (status stays administrative-only; asOf()/
            // active() never trust it either way, this only keeps the
            // label from contradicting what asOf() will itself compute).
            $resolvedEffectiveFrom = Carbon::parse($effectiveFrom ?? now())->startOfDay();
            $status = $resolvedEffectiveFrom->isAfter(Carbon::today()) ? 'scheduled' : 'active';

            // guardAgainstOverlap() (HasTemporalAssignment) throws if
            // this SubjectOffering already has an active teacher -- not
            // re-checked here.
            return TeacherAssignment::create([
                'employee_id' => $employee->id,
                'subject_offering_id' => $lockedOffering->id,
                'effective_from' => $resolvedEffectiveFrom,
                'status' => $status,
            ]);
        });
    }
}
