<?php

namespace App\Modules\Admissions\Events;

use App\Modules\Admissions\Models\Applicant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * No subscriber yet -- Sprint 4.3's ConvertApplicantToStudentAction is
 * the first anticipated listener, per the platform's established
 * practice of shipping an event's payload shape ahead of its first real
 * consumer.
 */
class ApplicantAccepted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Applicant $applicant) {}
}
