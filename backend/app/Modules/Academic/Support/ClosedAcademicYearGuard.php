<?php

namespace App\Modules\Academic\Support;

use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;

/**
 * The single implementation of "no new/modified records against a
 * closed Academic Year" (Sprint 4.1 Technical Specification, §"Closed
 * Academic Year Policy") -- every future consumer (Applicant, Sprint
 * 4.2; Enrollment, Sprint 4.4) calls assert() before persisting a
 * record scoped to an Academic Year, so the rule is enforced
 * identically everywhere rather than reimplemented per module.
 *
 * Deliberately not a Gate-based Laravel Policy despite the "policy"
 * name used in planning documents -- this is a data-integrity
 * invariant, not a permission check. It applies even to a user who
 * otherwise has full write access; naming it as a Policy (this
 * codebase's own convention, per AcademicYearPolicy/MergeRequestPolicy,
 * answers "may this user," never "is this state valid") would be
 * misleading.
 */
class ClosedAcademicYearGuard
{
    public function assert(int $academicYearId): void
    {
        $academicYear = AcademicYear::findOrFail($academicYearId);

        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException($academicYear);
        }
    }
}
