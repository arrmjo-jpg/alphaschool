<?php

namespace App\Modules\Admissions\Exceptions;

use RuntimeException;

/**
 * Raised by SubmitApplicationAction when the applied-for Grade Level is
 * not among those AcademicCatalogService::gradeLevelsForBranch()
 * returns for the chosen Branch -- a real business rule (a branch that
 * doesn't teach Grade 10 cannot receive a Grade 10 application), not a
 * generic validation failure.
 */
class GradeLevelNotOfferedAtBranchException extends RuntimeException
{
    public function __construct(int $gradeLevelId, int $branchId)
    {
        parent::__construct(
            "Grade Level [{$gradeLevelId}] is not offered at Branch [{$branchId}]."
        );
    }
}
