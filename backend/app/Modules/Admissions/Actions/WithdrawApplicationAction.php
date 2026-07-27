<?php

namespace App\Modules\Admissions\Actions;

use App\Modules\Admissions\Models\Applicant;

class WithdrawApplicationAction
{
    public function execute(Applicant $applicant): Applicant
    {
        $applicant->withdraw();

        return $applicant;
    }
}
