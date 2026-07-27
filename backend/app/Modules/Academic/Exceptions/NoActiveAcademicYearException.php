<?php

namespace App\Modules\Academic\Exceptions;

use RuntimeException;

/**
 * A fresh or mis-seeded deployment could have zero AcademicYear rows
 * with status = 'active', or (a data-integrity bug elsewhere) more than
 * one. Either way, AcademicCatalogService::activeAcademicYear() must
 * fail loudly here rather than let a null propagate into an Admissions/
 * Enrollment write downstream (Sprint 4.1 Technical Specification, §13).
 */
class NoActiveAcademicYearException extends RuntimeException
{
    public function __construct(int $activeCount)
    {
        parent::__construct(
            "Expected exactly one AcademicYear with status 'active', found {$activeCount}. Seed or correct the active Academic Year before proceeding.",
        );
    }
}
