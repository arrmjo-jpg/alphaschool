<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors PromoteEnrollmentAction exactly, minus the next-grade lookup
 * (same grade_level_id, new academic_year_id). Lives in Academic for the
 * same reason (ADR-0026, Sprint 4.4 Layering Review).
 */
class RepeatEnrollmentAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(Enrollment $enrollment, int $nextAcademicYearId): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $nextAcademicYearId): Enrollment {
            $locked = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Enrollment::STATUS_ACTIVE) {
                throw new EnrollmentNotActiveException($locked, 'repeat');
            }

            $this->academicCatalogService->assertAcademicYearIsOpen($nextAcademicYearId);

            $next = $locked->repeat($nextAcademicYearId);

            $student = Student::findOrFail($locked->student_id);
            $student->current_enrollment_id = $next->id;
            $student->save();

            return $next;
        });
    }
}
