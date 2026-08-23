<?php

namespace App\Modules\Academic\Exceptions;

use App\Modules\Academic\Models\GradeLevel;
use RuntimeException;

/**
 * Raised by PromoteEnrollmentAction (Sprint 4.4) when
 * AcademicCatalogService::nextGradeLevelForBranch() finds nothing --
 * either no active GradeLevel has a higher sequence_order at all, or
 * one exists but isn't offered at this Enrollment's own branch
 * (Independent Review Finding 1). Deliberately one exception for both
 * causes: from the caller's perspective the outcome is identical --
 * promotion cannot proceed, graduation is the correct transition
 * instead. Split into a more specific exception later only if a real
 * consumer (a distinct UI message, an admin report) needs to tell the
 * two causes apart; none does today.
 */
class NoNextGradeLevelException extends RuntimeException
{
    public function __construct(GradeLevel $current)
    {
        parent::__construct(
            "GradeLevel '{$current->name_en}' ({$current->public_id}) has no next grade level available for promotion at this Enrollment's branch -- either none exists in sequence, or the branch doesn't offer one. Graduate instead if this is genuinely the final grade the branch offers."
        );
    }
}
