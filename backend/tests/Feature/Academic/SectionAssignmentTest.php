<?php

use App\Core\ValueObjects\ReasonCode;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SectionAssignment;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Models\Enrollment;
use Database\Seeders\ReasonCodeSeeder;

it('creates a section assignment linking an active Enrollment to a Section', function () {
    $assignment = SectionAssignment::factory()->create();

    expect($assignment->status)->toBe('active')
        ->and($assignment->fresh()->enrollment)->toBeInstanceOf(Enrollment::class)
        ->and($assignment->fresh()->section)->toBeInstanceOf(Section::class);
});

/**
 * The Consistency Invariant (Phase 5 Architecture Pass, explicit user
 * requirement) -- enforced in SectionAssignment's own creation path, not
 * merely assumed from "it references Enrollment."
 */
it('rejects a SectionAssignment whose Enrollment and Section disagree on branch/academic_year/grade_level', function () {
    $enrollment = Enrollment::factory()->create();
    // A completely unrelated Section -- its own independent branch/year/grade.
    $mismatchedSection = Section::factory()->create();

    expect(fn () => SectionAssignment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'section_id' => $mismatchedSection->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a SectionAssignment that disagrees only on grade_level, with everything else matching', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();

    $enrollment = Enrollment::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => GradeLevel::factory()->create()->id,
    ]);

    $section = Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => GradeLevel::factory()->create()->id, // deliberately different
    ]);

    expect(fn () => SectionAssignment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'section_id' => $section->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a second active SectionAssignment for the same Enrollment', function () {
    $assignment = SectionAssignment::factory()->create();

    $anotherSection = Section::factory()->create([
        'branch_id' => $assignment->section->branch_id,
        'academic_year_id' => $assignment->section->academic_year_id,
        'grade_level_id' => $assignment->section->grade_level_id,
    ]);

    expect(fn () => SectionAssignment::factory()->create([
        'enrollment_id' => $assignment->enrollment_id,
        'section_id' => $anotherSection->id,
    ]))->toThrow(RuntimeException::class);
});

it('allows a mid-year section change once the prior assignment has been closed -- the real case HasTemporalAssignment exists for here', function () {
    $this->seed(ReasonCodeSeeder::class);

    $first = SectionAssignment::factory()->create(['effective_from' => now()->subMonths(3)]);

    $first->closeAssignment(new ReasonCode('section_transfer'), now()->subDay());

    $newSection = Section::factory()->create([
        'branch_id' => $first->section->branch_id,
        'academic_year_id' => $first->section->academic_year_id,
        'grade_level_id' => $first->section->grade_level_id,
    ]);

    $second = SectionAssignment::factory()->create([
        'enrollment_id' => $first->enrollment_id,
        'section_id' => $newSection->id,
        'effective_from' => now(),
    ]);

    expect($first->fresh()->status)->toBe('ended')
        ->and($second->status)->toBe('active');
});
