<?php

namespace App\Modules\Admissions\Exceptions;

use App\Modules\Admissions\Models\Applicant;
use RuntimeException;

/**
 * The double-conversion guard named as a real invariant in the
 * Blueprint (IMPLEMENTATION_PLAYBOOK.md Sprint 4.3) -- checked and
 * locked inside ConvertApplicantToStudentAction's own transaction, not
 * a read-then-write race.
 */
class ApplicantAlreadyConvertedException extends RuntimeException
{
    public function __construct(Applicant $applicant)
    {
        parent::__construct(
            "Applicant ({$applicant->public_id}) was already converted to a Student and cannot be converted again."
        );
    }
}
