<?php

use App\Modules\Academic\Exceptions\TermAcademicYearMismatchException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\Term;
use Illuminate\Database\QueryException;

it('creates a subject offering linking a Subject to a Section within a Term', function () {
    $offering = SubjectOffering::factory()->create();

    expect($offering->fresh()->subject)->toBeInstanceOf(Subject::class)
        ->and($offering->fresh()->section)->toBeInstanceOf(Section::class)
        ->and($offering->fresh()->term)->toBeInstanceOf(Term::class);
});

/**
 * The three-way Consistency Invariant (Subject Offering + Timetables
 * Architecture Pass, explicit user requirement) -- enforced in
 * SubjectOffering's own creation path, not merely assumed from "it
 * references Section/Term."
 */
it('rejects a SubjectOffering whose academic_year_id disagrees with its Section', function () {
    $academicYear = AcademicYear::factory()->create();
    $mismatchedSection = Section::factory()->create(); // its own independent academic year
    $term = Term::factory()->create(['academic_year_id' => $academicYear->id]);

    expect(fn () => SubjectOffering::factory()->create([
        'section_id' => $mismatchedSection->id,
        'term_id' => $term->id,
        'academic_year_id' => $academicYear->id,
    ]))->toThrow(TermAcademicYearMismatchException::class);
});

it('rejects a SubjectOffering whose academic_year_id disagrees with its Term', function () {
    $academicYear = AcademicYear::factory()->create();
    $section = Section::factory()->create(['academic_year_id' => $academicYear->id]);
    $mismatchedTerm = Term::factory()->create(); // its own independent academic year

    expect(fn () => SubjectOffering::factory()->create([
        'section_id' => $section->id,
        'term_id' => $mismatchedTerm->id,
        'academic_year_id' => $academicYear->id,
    ]))->toThrow(TermAcademicYearMismatchException::class);
});

it('rejects a duplicate offering of the same Subject to the same Section in the same Term', function () {
    $offering = SubjectOffering::factory()->create();

    expect(fn () => SubjectOffering::factory()->create([
        'subject_id' => $offering->subject_id,
        'section_id' => $offering->section_id,
        'term_id' => $offering->term_id,
        'academic_year_id' => $offering->academic_year_id,
    ]))->toThrow(QueryException::class);
});

it('allows the same Subject offered to two different Sections in the same Term', function () {
    $academicYear = AcademicYear::factory()->create();
    $subject = Subject::factory()->create();
    $term = Term::factory()->create(['academic_year_id' => $academicYear->id]);

    $sectionOne = Section::factory()->create(['academic_year_id' => $academicYear->id]);
    $sectionTwo = Section::factory()->create(['academic_year_id' => $academicYear->id]);

    $offeringOne = SubjectOffering::factory()->create([
        'subject_id' => $subject->id,
        'section_id' => $sectionOne->id,
        'term_id' => $term->id,
        'academic_year_id' => $academicYear->id,
    ]);
    $offeringTwo = SubjectOffering::factory()->create([
        'subject_id' => $subject->id,
        'section_id' => $sectionTwo->id,
        'term_id' => $term->id,
        'academic_year_id' => $academicYear->id,
    ]);

    expect($offeringOne->id)->not->toBe($offeringTwo->id);
});

it('carries no status/is_active column at all -- a definition, not a workflow', function () {
    $offering = SubjectOffering::factory()->create();

    expect($offering->getAttributes())->not->toHaveKey('status')
        ->and($offering->getAttributes())->not->toHaveKey('is_active');
});
