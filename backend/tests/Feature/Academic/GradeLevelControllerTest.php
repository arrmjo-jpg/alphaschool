<?php

use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * UI Sprint 1-B's second entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * a leaner suite than AcademicYearControllerTest's, since the shared
 * base's list/get/create/update/permission logic is already proven
 * there; this file's job is the boolean-status branch specifically,
 * plus GradeLevel's own field shape.
 */
function userWithCatalogPermissionForGradeLevel(bool $granted, ?Branch $branch = null): User
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

it('creates a grade level with is_active defaulting true', function () {
    $user = userWithCatalogPermissionForGradeLevel(true);

    $response = $this->actingAs($user)->postJson(route('academic.grade-levels.store'), [
        'code' => 'G1',
        'name_en' => 'Grade 1',
        'name_ar' => 'الصف الأول',
        'sequence_order' => 1,
    ]);

    $response->assertCreated();
    // Asserts the JSON response itself, not just the DB row -- see
    // AcademicYearControllerTest's identical comment for why
    // assertDatabaseHas alone doesn't exercise the $attributes-default bug.
    expect($response->json('is_active'))->toBeTrue();
    $this->assertDatabaseHas('grade_levels', ['code' => 'G1', 'is_active' => true]);
});

it('deactivates a grade level via the status endpoint', function () {
    $gradeLevel = GradeLevel::factory()->create(['is_active' => true]);
    $user = userWithCatalogPermissionForGradeLevel(true);

    $response = $this->actingAs($user)->patchJson(route('academic.grade-levels.status', ['id' => $gradeLevel->id]), [
        'status' => 'inactive',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('grade_levels', ['id' => $gradeLevel->id, 'is_active' => false]);
});

it('reactivates a grade level via the status endpoint', function () {
    $gradeLevel = GradeLevel::factory()->create(['is_active' => false]);
    $user = userWithCatalogPermissionForGradeLevel(true);

    $response = $this->actingAs($user)->patchJson(route('academic.grade-levels.status', ['id' => $gradeLevel->id]), [
        'status' => 'active',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('grade_levels', ['id' => $gradeLevel->id, 'is_active' => true]);
});

it('filters the list by status=active and status=inactive', function () {
    GradeLevel::factory()->create(['is_active' => true, 'sequence_order' => 1]);
    GradeLevel::factory()->create(['is_active' => false, 'sequence_order' => 2]);
    $user = userWithCatalogPermissionForGradeLevel(true);

    $activeResponse = $this->actingAs($user)->getJson(route('academic.grade-levels.index', ['status' => 'active']));
    $activeResponse->assertOk();
    expect($activeResponse->json('meta.total'))->toBe(1);

    $inactiveResponse = $this->actingAs($user)->getJson(route('academic.grade-levels.index', ['status' => 'inactive']));
    $inactiveResponse->assertOk();
    expect($inactiveResponse->json('meta.total'))->toBe(1);
});

it('rejects a duplicate sequence_order', function () {
    GradeLevel::factory()->create(['sequence_order' => 5]);
    $user = userWithCatalogPermissionForGradeLevel(true);

    $response = $this->actingAs($user)->postJson(route('academic.grade-levels.store'), [
        'code' => 'DUP',
        'name_en' => 'Duplicate',
        'name_ar' => 'مكرر',
        'sequence_order' => 5,
    ]);

    $response->assertUnprocessable();
});
