<?php

namespace App\Modules\Academic\Exceptions;

use RuntimeException;

/**
 * Raised by SubjectOffering's own creation path (booted()::saving()) --
 * the three-way Consistency Invariant BUS-0032 and the Subject Offering
 * + Timetables Architecture Pass both require: Section.academic_year_id,
 * Term.academic_year_id, and SubjectOffering's own stored
 * academic_year_id must all agree. Named for the invariant's originally
 * scoped 2-way case (BUS-0032's own required amendment: "a dedicated
 * TermAcademicYearMismatchException"), reused for both branches of the
 * now-three-way check (Section or Term disagreeing), since both are the
 * same underlying fact: SubjectOffering's own academic_year_id column
 * disagreeing with one of the two independently-scoped references it
 * stores alongside it.
 */
class TermAcademicYearMismatchException extends RuntimeException
{
    public function __construct(string $mismatchedEntity, int $subjectOfferingAcademicYearId, int $actualAcademicYearId)
    {
        parent::__construct(
            "SubjectOffering.academic_year_id ({$subjectOfferingAcademicYearId}) does not agree with ".
            "{$mismatchedEntity}.academic_year_id ({$actualAcademicYearId}) -- Section, Term, and SubjectOffering ".
            'must all agree on academic year.',
        );
    }
}
