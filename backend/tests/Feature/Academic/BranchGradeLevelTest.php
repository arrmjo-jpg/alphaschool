<?php

use App\Modules\Academic\Models\BranchGradeLevel;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Identity\Models\Branch;
use Illuminate\Database\QueryException;

it('rejects a duplicate branch/grade-level pair', function () {
    $branch = Branch::factory()->create();
    $gradeLevel = GradeLevel::factory()->create();

    BranchGradeLevel::factory()->create(['branch_id' => $branch->id, 'grade_level_id' => $gradeLevel->id]);

    expect(fn () => BranchGradeLevel::factory()->create(['branch_id' => $branch->id, 'grade_level_id' => $gradeLevel->id]))
        ->toThrow(QueryException::class);
});

it('restricts deleting a Branch that is still referenced by an active join', function () {
    $branch = Branch::factory()->create();
    $gradeLevel = GradeLevel::factory()->create();

    BranchGradeLevel::factory()->create(['branch_id' => $branch->id, 'grade_level_id' => $gradeLevel->id]);

    expect(fn () => $branch->delete())->toThrow(QueryException::class);
});

it('restricts deleting a GradeLevel that is still referenced by an active join', function () {
    $branch = Branch::factory()->create();
    $gradeLevel = GradeLevel::factory()->create();

    BranchGradeLevel::factory()->create(['branch_id' => $branch->id, 'grade_level_id' => $gradeLevel->id]);

    expect(fn () => $gradeLevel->delete())->toThrow(QueryException::class);
});
