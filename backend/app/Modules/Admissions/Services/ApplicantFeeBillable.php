<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Contracts\Billable;
use App\Modules\Admissions\Models\Applicant;

/**
 * The placeholder implementation named in IMPLEMENTATION_PLAYBOOK.md's
 * Sprint 4.3 Risks note: "keep the stub deliberately, visibly thin" --
 * reads the three fee columns added directly to `applicants`, no
 * invoicing logic, no separate fee-record model.
 */
class ApplicantFeeBillable implements Billable
{
    public function isPaid(Applicant $applicant): bool
    {
        return (bool) $applicant->fee_paid;
    }
}
