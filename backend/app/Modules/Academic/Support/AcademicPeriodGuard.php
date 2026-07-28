<?php

namespace App\Modules\Academic\Support;

use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Exceptions\ClosedTermException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Term;

/**
 * The single implementation of "no new/modified records against a
 * closed academic period" -- originally ClosedAcademicYearGuard (Sprint
 * 4.1 Technical Specification, §"Closed Academic Year Policy"), renamed
 * and generalized here (Subject Offering + Timetables Architecture
 * Pass) once Term became its second real consumer (BUS-0032's own
 * recorded preference for a generalized guard over a duplicate
 * ClosedTermGuard, matching Sprint A0's "second real consumer forces a
 * centralized fix" precedent for HasTemporalAssignment).
 *
 * assertAcademicYearIsOpen() is consumed by Applicant (Sprint 4.2, via
 * SubmitApplicationAction), ConvertApplicantToStudentAction (Sprint
 * 4.3), and PromoteEnrollmentAction/RepeatEnrollmentAction (Sprint 4.4,
 * target year only -- see those Actions' own docblocks).
 * assertTermIsOpen() is consumed by SubjectOffering's own creation path.
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
class AcademicPeriodGuard
{
    public function assertAcademicYearIsOpen(int $academicYearId): void
    {
        $academicYear = AcademicYear::findOrFail($academicYearId);

        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException($academicYear);
        }
    }

    public function assertTermIsOpen(int $termId): void
    {
        $term = Term::findOrFail($termId);

        if ($term->isClosed()) {
            throw new ClosedTermException($term);
        }
    }
}
