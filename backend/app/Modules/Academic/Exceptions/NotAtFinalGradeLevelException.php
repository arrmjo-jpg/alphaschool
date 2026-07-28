<?php

namespace App\Modules\Academic\Exceptions;

use App\Modules\Academic\Models\GradeLevel;
use RuntimeException;

/**
 * Raised by GraduateEnrollmentAction (Sprint 4.4) -- graduation is only
 * valid from the last GradeLevel in sequence, checked via
 * AcademicCatalogService::isFinalGradeLevel(). Promotion is the correct
 * transition from any earlier grade instead.
 */
class NotAtFinalGradeLevelException extends RuntimeException
{
    public function __construct(GradeLevel $current)
    {
        parent::__construct(
            "GradeLevel '{$current->name_en}' ({$current->public_id}) is not the final grade level -- graduation is not possible; promote instead."
        );
    }
}
