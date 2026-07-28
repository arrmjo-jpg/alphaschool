<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\HomeroomAssignment;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Lives in Academic (BUS-0019, Phase 5 Layering Review). Locks Section,
 * not Employee -- Section is HomeroomAssignment's own
 * temporalScopeAttributes() anchor (one active homeroom teacher per
 * Section at a time), matching AssignSectionAction's own
 * lock-the-real-anchor-row discipline.
 */
class AssignHomeroomTeacherAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(Section $section, Employee $employee): HomeroomAssignment
    {
        return DB::transaction(function () use ($section, $employee): HomeroomAssignment {
            $lockedSection = Section::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();

            $this->academicCatalogService->assertAcademicYearIsOpen($lockedSection->academic_year_id);

            // guardAgainstOverlap() (HasTemporalAssignment) throws if
            // this Section already has an active homeroom teacher --
            // not re-checked here.
            return HomeroomAssignment::create([
                'employee_id' => $employee->id,
                'section_id' => $lockedSection->id,
                'effective_from' => now(),
                'status' => 'active',
            ]);
        });
    }
}
