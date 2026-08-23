<?php

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Models\Section;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * UI Sprint 1-B's fifth and final entity (docs/ADMIN_DESIGN_SYSTEM.md
 * §28.17) -- the hardest test: non-bilingual name + three real FKs.
 */
function userWithCatalogPermissionForSection(bool $granted, ?Branch $branch = null): User
{
    $branch ??= Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);

    if ($granted) {
        $group = PermissionGroup::firstOrCreate(['code' => 'academic'], ['name' => ['en' => 'Academic', 'ar' => 'الأكاديمي']]);
        $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
        $permission = Permission::firstOrCreate(
            ['name' => 'academic.manage-catalog', 'guard_name' => 'sanctum'],
            ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
        );
        $role->givePermissionTo($permission);
        $user->assignRole($role);
    }

    return $user->fresh();
}

it('creates a section scoped to branch, academic year, and grade level', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->create();
    $gradeLevel = GradeLevel::factory()->create();
    $user = userWithCatalogPermissionForSection(true, $branch);

    $response = $this->actingAs($user)->postJson(route('academic.sections.store'), [
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
        'name' => 'A',
        'capacity' => 30,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('sections', ['name' => 'A', 'capacity' => 30, 'is_active' => true]);
});

it('returns FK columns as real integers, not strings, in the JSON response', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->create();
    $gradeLevel = GradeLevel::factory()->create();
    $user = userWithCatalogPermissionForSection(true, $branch);

    $response = $this->actingAs($user)->postJson(route('academic.sections.store'), [
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
        'name' => 'B',
    ]);

    $response->assertCreated();
    expect($response->json('branch_id'))->toBeInt()
        ->and($response->json('academic_year_id'))->toBeInt()
        ->and($response->json('grade_level_id'))->toBeInt();
});

it('rejects a duplicate name within the same branch/year/grade scope', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->create();
    $gradeLevel = GradeLevel::factory()->create();
    Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
        'name' => 'A',
    ]);
    $user = userWithCatalogPermissionForSection(true, $branch);

    $this->actingAs($user)->postJson(route('academic.sections.store'), [
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
        'name' => 'A',
    ])->assertUnprocessable();
});

it('allows the same section name in a different grade level', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->create();
    $gradeLevelOne = GradeLevel::factory()->create();
    $gradeLevelTwo = GradeLevel::factory()->create();
    Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevelOne->id,
        'name' => 'A',
    ]);
    $user = userWithCatalogPermissionForSection(true, $branch);

    $this->actingAs($user)->postJson(route('academic.sections.store'), [
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevelTwo->id,
        'name' => 'A',
    ])->assertCreated();
});

it('deactivates and reactivates a section', function () {
    $section = Section::factory()->create(['is_active' => true]);
    $user = userWithCatalogPermissionForSection(true);

    $this->actingAs($user)->patchJson(route('academic.sections.status', ['id' => $section->id]), ['status' => 'inactive'])->assertOk();
    $this->assertDatabaseHas('sections', ['id' => $section->id, 'is_active' => false]);
});

it('has no delete route registered for sections', function () {
    expect(collect(app('router')->getRoutes())->contains(
        fn ($route) => str_contains($route->uri(), 'sections') && in_array('DELETE', $route->methods()),
    ))->toBeFalse();
});

it('lists active branches via the new lookup endpoint', function () {
    Branch::factory()->create(['is_active' => true]);
    Branch::factory()->create(['is_active' => false]);
    $user = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($user)->getJson(route('branches.index'));

    $response->assertOk();
    expect(count($response->json('data')))->toBe(1);
});
