<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\HomeroomAssignment;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lives in Academic (BUS-0019, Phase 5 Layering Review). Locks Section,
 * not Employee -- Section is HomeroomAssignment's own
 * temporalScopeAttributes() anchor (one active homeroom teacher per
 * Section at a time), matching AssignSectionAction's own
 * lock-the-real-anchor-row discipline.
 *
 * $effectiveFrom is the first caller-specified date accepted by any
 * sibling Assign*Action (AssignSectionAction/
 * AssignTeacherToSubjectOfferingAction still hardcode now()) --
 * required by UI Sprint 2's own frozen Create form (§30.4: "an
 * async-select Employee picker + effective_from (date, defaulting
 * today) -- nothing else"), the first real consumer to expose this as
 * an editable field rather than an implicit "right now."
 */
class AssignHomeroomTeacherAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(Section $section, Employee $employee, Carbon|string|null $effectiveFrom = null): HomeroomAssignment
    {
        return DB::transaction(function () use ($section, $employee, $effectiveFrom): HomeroomAssignment {
            $lockedSection = Section::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();

            $this->academicCatalogService->assertAcademicYearIsOpen($lockedSection->academic_year_id);

            // Resolved once here (not left to the model's own
            // setEffectiveFromAttribute() mutator) because `status` must
            // agree with it: with effective_from now a real user-editable
            // date (not always "right now"), a future-dated assignment
            // created today is not yet in effect -- 'active' would be an
            // administrative label that lies until the date arrives,
            // exactly what HasTemporalAssignment's own docblock says
            // status must never do. `status` is still administrative-only
            // (asOf()/active() never trust it), but it should not
            // contradict what asOf() will itself compute the moment this
            // row exists.
            $resolvedEffectiveFrom = Carbon::parse($effectiveFrom ?? now())->startOfDay();
            $status = $resolvedEffectiveFrom->isAfter(Carbon::today()) ? 'scheduled' : 'active';

            // guardAgainstOverlap() (HasTemporalAssignment) throws if
            // this Section already has an active homeroom teacher --
            // not re-checked here.
            return HomeroomAssignment::create([
                'employee_id' => $employee->id,
                'section_id' => $lockedSection->id,
                'effective_from' => $resolvedEffectiveFrom,
                'status' => $status,
            ]);
        });
    }
}
