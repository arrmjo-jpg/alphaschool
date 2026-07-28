<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Exceptions\NoNextGradeLevelException;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Lives in Academic (Domain), not People (ADR-0026, Sprint 4.4 Layering
 * Review): resolving "what's the next grade" and "is the target year
 * open" are Academic-owned decisions; Enrollment/Student only ever
 * receive the resulting plain IDs, never a GradeLevel/AcademicYear
 * instance.
 *
 * Only the TARGET academic year is checked against
 * ClosedAcademicYearGuard, never the closing Enrollment's own
 * academic_year_id -- that guard exists to stop new commitments against
 * a closed year, not to freeze a historical record's own terminal
 * status transition, which is an outcome of that year ending, not a new
 * commitment within it. Promotion naturally runs once the old year has
 * already closed; blocking it on that basis would make the guard
 * prevent the exact workflow it should enable.
 */
class PromoteEnrollmentAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(Enrollment $enrollment, int $nextAcademicYearId): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $nextAcademicYearId): Enrollment {
            $locked = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            // Checked here first, matching ConvertApplicantToStudentAction's
            // own precedent (Sprint 4.3) -- Enrollment::promote()'s internal
            // assertActive() is a defensive backstop, not the primary
            // enforcement point, so a caller sees this exception before any
            // Academic-side lookup runs.
            if ($locked->status !== Enrollment::STATUS_ACTIVE) {
                throw new EnrollmentNotActiveException($locked, 'promote');
            }

            $this->academicCatalogService->assertAcademicYearIsOpen($nextAcademicYearId);

            $nextGradeLevel = $this->academicCatalogService->nextGradeLevel($locked->grade_level_id);

            if ($nextGradeLevel === null) {
                throw new NoNextGradeLevelException(GradeLevel::findOrFail($locked->grade_level_id));
            }

            $next = $locked->promote($nextAcademicYearId, $nextGradeLevel->id);

            $student = Student::findOrFail($locked->student_id);
            $student->current_enrollment_id = $next->id;
            $student->save();

            return $next;
        });
    }
}
