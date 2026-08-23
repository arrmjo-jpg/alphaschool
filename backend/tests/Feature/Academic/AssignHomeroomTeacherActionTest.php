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

it('honors a caller-specified effective_from instead of always defaulting to now, for UI Sprint 2 Create form (§30.4)', function () {
    $section = Section::factory()->create(['academic_year_id' => AcademicYear::factory()->active()->create()->id]);
    $employee = Employee::factory()->create();
    $pastDate = now()->subMonth()->toDateString();

    $assignment = app(AssignHomeroomTeacherAction::class)->execute($section, $employee, $pastDate);

    expect($assignment->effective_from->toDateString())->toBe($pastDate)
        ->and($assignment->status)->toBe('active');
});

it('marks a future-dated assignment scheduled, not active, so the Timeline badge never lies about a not-yet-started period (found live)', function () {
    // A real bug found live during UI Sprint 2's independent review:
    // effective_from became a genuinely user-editable date via the
    // Create form (§30.4), but this Action still unconditionally set
    // status='active' regardless of the date -- a homeroom teacher
    // assigned two months ahead would show a green "Active" badge today,
    // before the assignment had actually started. HasTemporalAssignment's
    // own docblock says status must never lie about what asOf() will
    // itself compute the moment the row exists.
    $section = Section::factory()->create(['academic_year_id' => AcademicYear::factory()->active()->create()->id]);
    $employee = Employee::factory()->create();
    $futureDate = now()->addMonths(2)->toDateString();

    $assignment = app(AssignHomeroomTeacherAction::class)->execute($section, $employee, $futureDate);

    expect($assignment->status)->toBe('scheduled')
        ->and($assignment->effective_from->toDateString())->toBe($futureDate);
});

it('still marks a same-day assignment active, not scheduled', function () {
    $section = Section::factory()->create(['academic_year_id' => AcademicYear::factory()->active()->create()->id]);
    $employee = Employee::factory()->create();

    $assignment = app(AssignHomeroomTeacherAction::class)->execute($section, $employee, now()->toDateString());

    expect($assignment->status)->toBe('active');
});
