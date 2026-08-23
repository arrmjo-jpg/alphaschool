<?php

namespace App\Modules\People\Events;

use App\Modules\People\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched from Enrollment::graduate() itself via DB::afterCommit()
 * (Sprint 4.4). Distinct from StudentGraduated, mirroring
 * EnrollmentWithdrawn/StudentWithdrawn's own separation.
 */
class EnrollmentGraduated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Enrollment $enrollment) {}
}
