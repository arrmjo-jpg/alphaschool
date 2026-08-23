<?php

namespace App\Modules\Academic\Actions;

use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Lives in Academic for consistency with the other three transition
 * Actions (ADR-0026, Sprint 4.4 Layering Review), though it needs no
 * Academic-side lookup of its own -- withdrawal is purely a People-side
 * fact (Enrollment/Student both closing out). No AcademicPeriodGuard
 * check: withdrawal records that an enrollment ended, it doesn't commit
 * anything new against the enrollment's academic year, so the guard's
 * "no new commitments against a closed year" purpose doesn't apply here
 * (see PromoteEnrollmentAction's docblock for the same reasoning applied
 * to promotion's own closing write).
 */
class WithdrawEnrollmentAction
{
    public function execute(Enrollment $enrollment): void
    {
        DB::transaction(function () use ($enrollment): void {
            $locked = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Enrollment::STATUS_ACTIVE) {
                throw new EnrollmentNotActiveException($locked, 'withdraw');
            }

            $locked->withdraw();
            Student::findOrFail($locked->student_id)->withdraw();
        });
    }
}
