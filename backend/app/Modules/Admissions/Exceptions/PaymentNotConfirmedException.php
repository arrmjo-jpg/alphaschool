<?php

namespace App\Modules\Admissions\Exceptions;

use App\Modules\Admissions\Models\Applicant;
use RuntimeException;

class PaymentNotConfirmedException extends RuntimeException
{
    public function __construct(Applicant $applicant)
    {
        parent::__construct(
            "Applicant ({$applicant->public_id})'s registration fee is not confirmed as paid -- cannot convert to a Student."
        );
    }
}
