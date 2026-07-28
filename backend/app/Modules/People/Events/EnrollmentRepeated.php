<?php

namespace App\Modules\People\Events;

use App\Modules\People\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched from Enrollment::repeat() itself via DB::afterCommit()
 * (Sprint 4.4), mirroring EnrollmentPromoted exactly.
 */
class EnrollmentRepeated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $previous,
        public readonly Enrollment $next,
    ) {}
}
