<?php

namespace App\Modules\Admissions\Contracts;

use App\Modules\Admissions\Models\Applicant;

/**
 * The thinnest possible fee/payment check (IMPLEMENTATION_PLAYBOOK.md
 * Sprint 4.3) -- deliberately not "invoicing," just the single point
 * ConvertApplicantToStudentAction checks before converting. Real
 * invoicing is Finance's own Phase 7 concern; this interface exists so
 * Finance can implement it properly later without ConvertApplicantToStudentAction
 * changing at all -- only the container binding moves.
 */
interface Billable
{
    public function isPaid(Applicant $applicant): bool;
}
