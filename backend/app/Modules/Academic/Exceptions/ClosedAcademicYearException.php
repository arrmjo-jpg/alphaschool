<?php

namespace App\Modules\Academic\Exceptions;

use App\Modules\Academic\Models\AcademicYear;
use RuntimeException;

/**
 * Raised by AcademicPeriodGuard::assertAcademicYearIsOpen() -- the
 * single, shared enforcement of "no new/modified records against a
 * closed Academic Year" every future consumer (Applicant, Enrollment,
 * ...) must call before persisting a record scoped to an Academic Year.
 * Carries the year's public_id/name so a caller several layers removed
 * from this guard can diagnose the failure without a second query.
 */
class ClosedAcademicYearException extends RuntimeException
{
    public function __construct(AcademicYear $academicYear)
    {
        parent::__construct(
            "Academic Year '{$academicYear->name_en}' ({$academicYear->public_id}) is closed and cannot accept new or modified records.",
        );
    }
}
