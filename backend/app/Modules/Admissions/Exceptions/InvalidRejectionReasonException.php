<?php

namespace App\Modules\Admissions\Exceptions;

use RuntimeException;

/**
 * Independent Review fix: Applicant::reject() previously accepted any
 * reason_codes.id satisfying the foreign key, with no check that it
 * belongs to the 'application_rejection' context -- a caller could cite
 * a guardian_student_relationship reason (e.g. 'custody_change') on a
 * rejected application. Raised when the supplied id doesn't resolve to
 * an active ReasonCode in that context.
 */
class InvalidRejectionReasonException extends RuntimeException
{
    public function __construct(int $reasonCodeId)
    {
        parent::__construct(
            "Reason code [{$reasonCodeId}] is not a valid, active reason in the 'application_rejection' context."
        );
    }
}
