<?php

namespace App\Modules\Academic\Events;

use App\Modules\Academic\Models\AcademicYear;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched once, from AcademicYear::close() -- no subscribers yet
 * (Report Card finalization, Phase 5, is the first anticipated
 * listener), but the contract exists now per this platform's
 * established practice of shipping an event's payload shape ahead of
 * its first real consumer (Sprint 4.1 Technical Specification, §11).
 */
class AcademicYearClosed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AcademicYear $academicYear) {}
}
