<?php

namespace App\Modules\Academic\Exceptions;

use App\Modules\Academic\Models\GradeLevel;
use RuntimeException;

/**
 * Raised by GraduateEnrollmentAction (Sprint 4.4) -- graduation is only
 * valid from the last GradeLevel this Enrollment's own branch offers,
 * checked via AcademicCatalogService::isFinalGradeLevelForBranch()
 * (branch-scoped since Independent Review Finding 1 -- a higher grade
 * existing elsewhere in the system, but not at this branch, does not
 * block graduation here). Promotion is the correct transition from any
 * earlier grade instead.
 */
class NotAtFinalGradeLevelException extends RuntimeException
{
    public function __construct(GradeLevel $current)
    {
        parent::__construct(
            "The student is not at the last grade level this branch offers ('{$current->name_en}' / {$current->public_id} is not final at this Enrollment's branch) -- graduation is not possible; promote instead."
        );
    }
}
