<?php

namespace App\Modules\Admissions\Exceptions;

use App\Modules\Admissions\Models\Applicant;
use RuntimeException;

class ApplicantNotAcceptedException extends RuntimeException
{
    public function __construct(Applicant $applicant)
    {
        parent::__construct(
            "Applicant ({$applicant->public_id}) is not in the 'accepted' status and cannot be converted to a Student."
        );
    }
}
