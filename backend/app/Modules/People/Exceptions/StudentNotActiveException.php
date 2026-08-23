<?php

namespace App\Modules\People\Exceptions;

use App\Modules\People\Models\Student;
use RuntimeException;

/**
 * Mirrors EnrollmentNotActiveException's throw-not-no-op discipline
 * (Sprint 4.4) -- Student::withdraw()/graduate() are only reachable from
 * STATUS_ACTIVE. In practice this is always already prevented by the
 * calling Action's own prior EnrollmentNotActiveException check, since
 * Student and its current Enrollment transition together -- this guard
 * exists so Student's own contract is self-defending, not solely reliant
 * on every future caller getting the ordering right.
 */
class StudentNotActiveException extends RuntimeException
{
    public function __construct(Student $student, string $attemptedTransition)
    {
        parent::__construct(
            "Student ({$student->public_id}) is not active (current status: '{$student->lifecycle_status}') -- cannot {$attemptedTransition} it."
        );
    }
}
