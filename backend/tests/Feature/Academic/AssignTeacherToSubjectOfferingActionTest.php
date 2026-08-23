<?php

use App\Modules\Academic\Actions\AssignTeacherToSubjectOfferingAction;
use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\Term;
use App\Modules\People\Models\Employee;

it('assigns an Employee as the teacher of a SubjectOffering scoped to an active year', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $offering = SubjectOffering::factory()->create([
        'section_id' => Section::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'term_id' => Term::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'academic_year_id' => $academicYear->id,
    ]);
    $employee = Employee::factory()->create();

    $assignment = app(AssignTeacherToSubjectOfferingAction::class)->execute($offering, $employee);

    expect($assignment->employee_id)->toBe($employee->id)
        ->and($assignment->subject_offering_id)->toBe($offering->id)
        ->and($assignment->status)->toBe('active');
});

it('refuses to assign a teacher to a SubjectOffering scoped to a closed Academic Year', function () {
    $academicYear = AcademicYear::factory()->closed()->create();
    $offering = SubjectOffering::factory()->create([
        'section_id' => Section::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'term_id' => Term::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'academic_year_id' => $academicYear->id,
    ]);
    $employee = Employee::factory()->create();

    expect(fn () => app(AssignTeacherToSubjectOfferingAction::class)->execute($offering, $employee))
        ->toThrow(ClosedAcademicYearException::class);
});

it('honors a caller-specified effective_from instead of always defaulting to now, for UI Sprint 2 Create form (§32.10)', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $offering = SubjectOffering::factory()->create([
        'section_id' => Section::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'term_id' => Term::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'academic_year_id' => $academicYear->id,
    ]);
    $employee = Employee::factory()->create();
    $pastDate = now()->subMonth()->toDateString();

    $assignment = app(AssignTeacherToSubjectOfferingAction::class)->execute($offering, $employee, $pastDate);

    expect($assignment->effective_from->toDateString())->toBe($pastDate)
        ->and($assignment->status)->toBe('active');
});

it('marks a future-dated assignment scheduled, not active, closing the exact gap independently fixed in the two sibling Actions (§32.10)', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $offering = SubjectOffering::factory()->create([
        'section_id' => Section::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'term_id' => Term::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'academic_year_id' => $academicYear->id,
    ]);
    $employee = Employee::factory()->create();
    $futureDate = now()->addMonths(2)->toDateString();

    $assignment = app(AssignTeacherToSubjectOfferingAction::class)->execute($offering, $employee, $futureDate);

    expect($assignment->status)->toBe('scheduled')
        ->and($assignment->effective_from->toDateString())->toBe($futureDate);
});

it('still marks a same-day assignment active, not scheduled', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $offering = SubjectOffering::factory()->create([
        'section_id' => Section::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'term_id' => Term::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'academic_year_id' => $academicYear->id,
    ]);
    $employee = Employee::factory()->create();

    $assignment = app(AssignTeacherToSubjectOfferingAction::class)->execute($offering, $employee, now()->toDateString());

    expect($assignment->status)->toBe('active');
});
