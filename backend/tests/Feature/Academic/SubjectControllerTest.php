<?php

use App\Modules\Academic\Models\Subject;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * UI Sprint 1-B's third entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * the agreed checkpoint proving the shared base generalizes to a
 * second boolean-status Reference Data entity with zero structural
 * change; this suite is intentionally lean since the shared base's
 * own behavior is already proven by AcademicYearControllerTest/
 * GradeLevelControllerTest.
 */
function userWithCatalogPermissionForSubject(bool $granted, ?Branch $branch = null): User
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

it('creates a subject with is_active defaulting true', function () {
    $user = userWithCatalogPermissionForSubject(true);

    $response = $this->actingAs($user)->postJson(route('academic.subjects.store'), [
        'code' => 'MATH',
        'name_en' => 'Mathematics',
        'name_ar' => 'الرياضيات',
    ]);

    $response->assertCreated();
    // Asserts the JSON response itself, not just the DB row -- see
    // AcademicYearControllerTest's identical comment for why
    // assertDatabaseHas alone doesn't exercise the $attributes-default bug.
    expect($response->json('is_active'))->toBeTrue();
    $this->assertDatabaseHas('subjects', ['code' => 'MATH', 'is_active' => true]);
});

it('rejects a duplicate code', function () {
    Subject::factory()->create(['code' => 'MATH']);
    $user = userWithCatalogPermissionForSubject(true);

    $this->actingAs($user)->postJson(route('academic.subjects.store'), [
        'code' => 'MATH',
        'name_en' => 'Duplicate Math',
        'name_ar' => 'رياضيات مكررة',
    ])->assertUnprocessable();
});

it('deactivates and reactivates a subject', function () {
    $subject = Subject::factory()->create(['is_active' => true]);
    $user = userWithCatalogPermissionForSubject(true);

    $this->actingAs($user)->patchJson(route('academic.subjects.status', ['id' => $subject->id]), ['status' => 'inactive'])->assertOk();
    $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'is_active' => false]);

    $this->actingAs($user)->patchJson(route('academic.subjects.status', ['id' => $subject->id]), ['status' => 'active'])->assertOk();
    $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'is_active' => true]);
});

it('denies access for a user without the permission', function () {
    $user = userWithCatalogPermissionForSubject(false);

    $this->actingAs($user)->getJson(route('academic.subjects.index'))->assertForbidden();
});
