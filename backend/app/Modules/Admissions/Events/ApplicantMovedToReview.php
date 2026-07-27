<?php

namespace App\Modules\Admissions\Events;

use App\Modules\Admissions\Models\Applicant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicantMovedToReview
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Applicant $applicant) {}
}
