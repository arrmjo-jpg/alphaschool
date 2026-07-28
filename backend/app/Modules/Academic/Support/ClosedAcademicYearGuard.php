<?php

namespace App\Modules\Academic\Support;

use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;

/**
 * The single implementation of "no new/modified records against a
 * closed Academic Year" (Sprint 4.1 Technical Specification, §"Closed
 * Academic Year Policy"). Consumed by Applicant (Sprint 4.2, via
 * SubmitApplicationAction) and by ConvertApplicantToStudentAction
 * (Sprint 4.3). Sprint 4.4's PromoteEnrollmentAction/
 * RepeatEnrollmentAction also call assert() -- but only against the
 * *target* academic year, never Enrollment's own closing year, and
 * WithdrawEnrollmentAction/GraduateEnrollmentAction don't call it at
 * all (Independent Review Finding 2, corrected 2026-07-27: this guard
 * stops new commitments against a closed year, it doesn't freeze a
 * historical record's own terminal transition, which is an outcome of
 * that year ending, not a new commitment within it -- see
 * PromoteEnrollmentAction's own docblock for the full reasoning).
 * Enrollment itself never calls this guard directly.
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
