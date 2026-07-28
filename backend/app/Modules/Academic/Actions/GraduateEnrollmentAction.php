<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Exceptions\NotAtFinalGradeLevelException;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Lives in Academic (ADR-0026, Sprint 4.4 Layering Review) -- the one
 * Academic-side check graduation needs is "is this the final grade
 * level," via AcademicCatalogService. No ClosedAcademicYearGuard check,
 * same reasoning as WithdrawEnrollmentAction.
 */
class GraduateEnrollmentAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(Enrollment $enrollment): void
    {
        DB::transaction(function () use ($enrollment): void {
            $locked = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Enrollment::STATUS_ACTIVE) {
                throw new EnrollmentNotActiveException($locked, 'graduate');
            }

            if (! $this->academicCatalogService->isFinalGradeLevel($locked->grade_level_id)) {
                throw new NotAtFinalGradeLevelException(GradeLevel::findOrFail($locked->grade_level_id));
            }

            $locked->graduate();
            Student::findOrFail($locked->student_id)->graduate();
        });
    }
}
