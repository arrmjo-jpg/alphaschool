<?php

namespace App\Modules\People\Exceptions;

use App\Modules\People\Models\Enrollment;
use RuntimeException;

/**
 * Sprint 4.4 Architecture Pass, §"Withdrawal and Graduation are
 * terminal" (explicit user requirement, 2026-07-27): promote()/repeat()/
 * withdraw()/graduate() are only valid from Enrollment::STATUS_ACTIVE.
 * Unlike Applicant::convert()'s own defensive-backstop no-op (Sprint
 * 4.3), every one of these four transitions throws on a non-active
 * Enrollment -- a promotion/repetition/withdrawal/graduation attempt
 * against an already-decided Enrollment is a real caller mistake that
 * must surface loudly, not disappear silently.
 */
class EnrollmentNotActiveException extends RuntimeException
{
    public function __construct(Enrollment $enrollment, string $attemptedTransition)
    {
        parent::__construct(
            "Enrollment ({$enrollment->public_id}) is not active (current status: '{$enrollment->status}') -- cannot {$attemptedTransition} it."
        );
    }
}
