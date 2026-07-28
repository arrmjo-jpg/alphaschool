<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Locks SubjectOffering, not Employee -- SubjectOffering is
 * TeacherAssignment's own temporalScopeAttributes() anchor (one active
 * teacher per SubjectOffering at a time), matching
 * AssignHomeroomTeacherAction's own lock-the-real-anchor-row discipline.
 */
class AssignTeacherToSubjectOfferingAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(SubjectOffering $subjectOffering, Employee $employee): TeacherAssignment
    {
        return DB::transaction(function () use ($subjectOffering, $employee): TeacherAssignment {
            $lockedOffering = SubjectOffering::query()->whereKey($subjectOffering->id)->lockForUpdate()->firstOrFail();

            $this->academicCatalogService->assertAcademicYearIsOpen($lockedOffering->academic_year_id);

            // guardAgainstOverlap() (HasTemporalAssignment) throws if
            // this SubjectOffering already has an active teacher -- not
            // re-checked here.
            return TeacherAssignment::create([
                'employee_id' => $employee->id,
                'subject_offering_id' => $lockedOffering->id,
                'effective_from' => now(),
                'status' => 'active',
            ]);
        });
    }
}
