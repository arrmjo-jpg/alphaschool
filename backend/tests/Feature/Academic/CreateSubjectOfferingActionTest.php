<?php

use App\Modules\Academic\Actions\CreateSubjectOfferingAction;
use App\Modules\Academic\Exceptions\ClosedTermException;
use App\Modules\Academic\Exceptions\TermAcademicYearMismatchException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\Term;

it('creates a subject offering for a consistent Subject/Section/Term triple', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $section = Section::factory()->create(['academic_year_id' => $academicYear->id]);
    $term = Term::factory()->create(['academic_year_id' => $academicYear->id]);
    $subject = Subject::factory()->create();

    $offering = app(CreateSubjectOfferingAction::class)->execute($subject, $section, $term);

    expect($offering->subject_id)->toBe($subject->id)
        ->and($offering->section_id)->toBe($section->id)
        ->and($offering->term_id)->toBe($term->id)
        ->and($offering->academic_year_id)->toBe($academicYear->id);
});

it('refuses to create a subject offering against a closed Term', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $section = Section::factory()->create(['academic_year_id' => $academicYear->id]);
    $closedTerm = Term::factory()->closed()->create(['academic_year_id' => $academicYear->id]);
    $subject = Subject::factory()->create();

    expect(fn () => app(CreateSubjectOfferingAction::class)->execute($subject, $section, $closedTerm))
        ->toThrow(ClosedTermException::class);
});

it('propagates the three-way Consistency Invariant when Section and Term disagree on academic year', function () {
    $section = Section::factory()->create(); // its own independent academic year
    $mismatchedTerm = Term::factory()->create(); // a different, independent academic year
    $subject = Subject::factory()->create();

    expect(fn () => app(CreateSubjectOfferingAction::class)->execute($subject, $section, $mismatchedTerm))
        ->toThrow(TermAcademicYearMismatchException::class);
});
