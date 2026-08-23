<?php

namespace App\Modules\Admissions\Events;

use App\Modules\Admissions\Models\Applicant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired from Applicant::convert() itself, distinct from People's own
 * StudentEnrolled (fired by ConvertApplicantToStudentAction) -- matches
 * every other Applicant transition method's existing pattern (each
 * dispatches its own event), and lets a future Admissions-side listener
 * (e.g. conversion-funnel analytics) subscribe without depending on
 * People's own event.
 */
class ApplicantConverted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Applicant $applicant) {}
}
