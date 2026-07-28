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
