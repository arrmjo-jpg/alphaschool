<?php

namespace App\Modules\People\Events;

use App\Modules\People\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched from Enrollment::withdraw() itself via DB::afterCommit()
 * (Sprint 4.4). Distinct from StudentWithdrawn (dispatched separately
 * by Student::withdraw()) -- mirrors StudentEnrolled/ApplicantConverted
 * being two separate events for two separate aggregate facts (Sprint
 * 4.3 precedent) rather than one event serving both consumers.
 */
class EnrollmentWithdrawn
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Enrollment $enrollment) {}
}
