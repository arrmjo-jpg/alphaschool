<?php

namespace App\Modules\Admissions\Actions;

use App\Modules\Admissions\Models\Applicant;

/**
 * The accepted/rejected decision (Sprint 4.2's own approved scope --
 * conversion to Student is Sprint 4.3's own action, not this one).
 */
class DecideApplicationAction
{
    public function accept(Applicant $applicant): Applicant
    {
        $applicant->accept();

        return $applicant;
    }

    public function reject(Applicant $applicant, int $reasonCodeId): Applicant
    {
        $applicant->reject($reasonCodeId);

        return $applicant;
    }
}
