<?php

namespace App\Modules\People\Events;

use App\Modules\People\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched from Enrollment::promote() itself via DB::afterCommit()
 * (Sprint 4.4) -- matches ApplicantConverted's "the aggregate dispatches
 * its own event" precedent, and applies Sprint 4.3's own Finding 2
 * lesson (afterCommit from the start, not discovered by review after
 * the fact).
 */
class EnrollmentPromoted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $previous,
        public readonly Enrollment $next,
    ) {}
}
