<?php

use App\Modules\Academic\Actions\AssignSectionAction;
use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Models\Section;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;

/**
 * UI Sprint 2 (docs/ADMIN_DESIGN_SYSTEM.md §31.11) -- this Action
 * predates UI Sprint 2 entirely (Phase 5 Sprint B) and had never been
 * exercised directly by a dedicated test before now (only
 * SectionAssignmentTest.php's own model-level tests and
 * AssignSectionActionConcurrencyTest.php's lock proof existed). Focused
 * on what §31.11 actually added -- effective_from/status computation --
 * not re-testing the Consistency Invariant or overlap guard, both
 * already covered at the model level in SectionAssignmentTest.php.
 */
function matchingEnrollmentAndSection(string $academicYearStatus = 'active'): array
{
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->create(['status' => $academicYearStatus]);
    $gradeLevel = GradeLevel::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
    ]);

    $section = Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
    ]);

    return [$enrollment, $section];
}

it('assigns a Section to an active Enrollment whose Academic Year is open', function () {
    [$enrollment, $section] = matchingEnrollmentAndSection();

    $assignment = app(AssignSectionAction::class)->execute($enrollment, $section);

    expect($assignment->enrollment_id)->toBe($enrollment->id)
        ->and($assignment->section_id)->toBe($section->id)
        ->and($assignment->status)->toBe('active');
});

it('refuses to assign a Section scoped to a closed Academic Year', function () {
    [$enrollment, $section] = matchingEnrollmentAndSection('closed');

    expect(fn () => app(AssignSectionAction::class)->execute($enrollment, $section))
        ->toThrow(ClosedAcademicYearException::class);
});

it('refuses to assign a Section to a withdrawn Enrollment', function () {
    [$enrollment, $section] = matchingEnrollmentAndSection();
    $enrollment->update(['status' => Enrollment::STATUS_WITHDRAWN]);

    expect(fn () => app(AssignSectionAction::class)->execute($enrollment, $section))
        ->toThrow(EnrollmentNotActiveException::class);
});

it('honors a caller-specified effective_from instead of always defaulting to now, for UI Sprint 2 Create form (§31.11)', function () {
    [$enrollment, $section] = matchingEnrollmentAndSection();
    $pastDate = now()->subMonth()->toDateString();

    $assignment = app(AssignSectionAction::class)->execute($enrollment, $section, $pastDate);

    expect($assignment->effective_from->toDateString())->toBe($pastDate)
        ->and($assignment->status)->toBe('active');
});

it('marks a future-dated assignment scheduled, not active, closing the exact gap independent review found in AssignHomeroomTeacherAction (§31.11)', function () {
    [$enrollment, $section] = matchingEnrollmentAndSection();
    $futureDate = now()->addMonths(2)->toDateString();

    $assignment = app(AssignSectionAction::class)->execute($enrollment, $section, $futureDate);

    expect($assignment->status)->toBe('scheduled')
        ->and($assignment->effective_from->toDateString())->toBe($futureDate);
});

it('still marks a same-day assignment active, not scheduled', function () {
    [$enrollment, $section] = matchingEnrollmentAndSection();

    $assignment = app(AssignSectionAction::class)->execute($enrollment, $section, now()->toDateString());

    expect($assignment->status)->toBe('active');
});
