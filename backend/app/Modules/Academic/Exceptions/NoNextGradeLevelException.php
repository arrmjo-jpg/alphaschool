<?php

namespace App\Modules\Academic\Exceptions;

use App\Modules\Academic\Models\GradeLevel;
use RuntimeException;

/**
 * Raised by PromoteEnrollmentAction (Sprint 4.4) when
 * AcademicCatalogService::nextGradeLevel() finds no active GradeLevel
 * with a higher sequence_order -- promotion has nowhere to go.
 * Graduation is the correct transition from this GradeLevel instead.
 */
class NoNextGradeLevelException extends RuntimeException
{
    public function __construct(GradeLevel $current)
    {
        parent::__construct(
            "GradeLevel '{$current->name_en}' ({$current->public_id}) has no next grade level in sequence -- promotion is not possible; graduate instead."
        );
    }
}
