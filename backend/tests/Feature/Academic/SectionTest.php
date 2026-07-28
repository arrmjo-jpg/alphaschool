<?php

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Models\Section;
use App\Modules\Identity\Models\Branch;
use Illuminate\Database\QueryException;

it('creates a section scoped to a branch, academic year, and grade level', function () {
    $section = Section::factory()->create(['name' => 'A', 'capacity' => 25]);

    expect($section->name)->toBe('A')
        ->and($section->capacity)->toBe(25)
        ->and($section->is_active)->toBeTrue();
});

it('rejects a duplicate name within the same branch, academic year, and grade level', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $gradeLevel = GradeLevel::factory()->create();

    Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
        'name' => 'A',
    ]);

    expect(fn () => Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
        'name' => 'A',
    ]))->toThrow(QueryException::class);
});

it('allows the same section name across different academic years -- a Section is per-year, not a permanent catalog', function () {
    $branch = Branch::factory()->create();
    $gradeLevel = GradeLevel::factory()->create();
    $yearOne = AcademicYear::factory()->active()->create();
    $yearTwo = AcademicYear::factory()->active()->create();

    $sectionYearOne = Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $yearOne->id,
        'grade_level_id' => $gradeLevel->id,
        'name' => 'A',
    ]);

    $sectionYearTwo = Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $yearTwo->id,
        'grade_level_id' => $gradeLevel->id,
        'name' => 'A',
    ]);

    expect($sectionYearOne->id)->not->toBe($sectionYearTwo->id);
});

it('deactivates instead of requiring deletion, matching GradeLevel/Branch\'s own reference-entity convention', function () {
    $section = Section::factory()->create();

    $section->update(['is_active' => false]);

    expect($section->fresh()->is_active)->toBeFalse();
});

it('logs name/capacity/is_active changes via activitylog, suppressing empty diffs', function () {
    $section = Section::factory()->create(['capacity' => 20]);

    $section->update(['capacity' => 22]);
    expect($section->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $section->activitiesAsSubject()->count();
    $section->update(['capacity' => 22]); // no real change
    expect($section->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});
