<?php

use App\Modules\Academic\Actions\AssignHomeroomTeacherAction;
use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Section;
use App\Modules\People\Models\Employee;

it('assigns an Employee as the homeroom teacher of an active-year Section', function () {
    $section = Section::factory()->create(['academic_year_id' => AcademicYear::factory()->active()->create()->id]);
    $employee = Employee::factory()->create();

    $assignment = app(AssignHomeroomTeacherAction::class)->execute($section, $employee);

    expect($assignment->employee_id)->toBe($employee->id)
        ->and($assignment->section_id)->toBe($section->id)
        ->and($assignment->status)->toBe('active');
});

it('refuses to assign a homeroom teacher to a Section scoped to a closed Academic Year', function () {
    $section = Section::factory()->create(['academic_year_id' => AcademicYear::factory()->closed()->create()->id]);
    $employee = Employee::factory()->create();

    expect(fn () => app(AssignHomeroomTeacherAction::class)->execute($section, $employee))
        ->toThrow(ClosedAcademicYearException::class);
});
