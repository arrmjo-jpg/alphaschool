<?php

use App\Modules\Academic\Actions\AssignSectionAction;
use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Models\Section;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;

function consistentEnrollmentAndSection(): array
{
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $gradeLevel = GradeLevel::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
        'status' => Enrollment::STATUS_ACTIVE,
    ]);

    $section = Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
    ]);

    return [$enrollment, $section];
}

it('assigns an active Enrollment to a consistent Section', function () {
    [$enrollment, $section] = consistentEnrollmentAndSection();

    $assignment = app(AssignSectionAction::class)->execute($enrollment, $section);

    expect($assignment->enrollment_id)->toBe($enrollment->id)
        ->and($assignment->section_id)->toBe($section->id)
        ->and($assignment->status)->toBe('active');
});

it('refuses to assign a Section to an Enrollment that is not active', function () {
    [$enrollment, $section] = consistentEnrollmentAndSection();
    $enrollment->withdraw();

    expect(fn () => app(AssignSectionAction::class)->execute($enrollment->fresh(), $section))
        ->toThrow(EnrollmentNotActiveException::class);
});

it('refuses to assign a Section scoped to a closed Academic Year', function () {
    $branch = Branch::factory()->create();
    $closedYear = AcademicYear::factory()->closed()->create();
    $gradeLevel = GradeLevel::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => AcademicYear::factory()->active()->create()->id,
        'grade_level_id' => $gradeLevel->id,
        'status' => Enrollment::STATUS_ACTIVE,
    ]);

    $section = Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $closedYear->id,
        'grade_level_id' => $gradeLevel->id,
    ]);

    expect(fn () => app(AssignSectionAction::class)->execute($enrollment, $section))
        ->toThrow(ClosedAcademicYearException::class);
});

it('propagates the Consistency Invariant when the Enrollment and Section disagree', function () {
    $enrollment = Enrollment::factory()->create(['status' => Enrollment::STATUS_ACTIVE]);
    $mismatchedSection = Section::factory()->create();

    expect(fn () => app(AssignSectionAction::class)->execute($enrollment, $mismatchedSection))
        ->toThrow(InvalidArgumentException::class);
});
