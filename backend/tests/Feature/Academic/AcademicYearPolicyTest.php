<?php

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * Mirrors userWithOtpPermission()'s pattern (ConfigurationControllerTest)
 * -- an ad-hoc role/permission grant scoped to this test file, since
 * academic.manage-catalog is seeded but deliberately unassigned to any
 * real role (Sprint 4.1 Technical Specification, "Open item").
 */
function userWithAcademicCatalogPermission(bool $grant, ?Branch $branch = null): User
{
    $branch ??= Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);

    // The permission must exist for the guard regardless of $grant --
    // Spatie's hasPermissionTo() throws PermissionDoesNotExist for an
    // unknown permission name rather than returning false, so the
    // "denied" case still needs the permission seeded, just never
    // assigned to this user's role.
    $group = PermissionGroup::firstOrCreate(['code' => 'academic-test'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'academic.manage-catalog', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );

    if ($grant) {
        $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
        $role->givePermissionTo($permission);
        $user->assignRole($role);
    }

    return $user;
}

it('grants create/update/close to a user holding academic.manage-catalog', function () {
    $user = userWithAcademicCatalogPermission(grant: true);
    $academicYear = AcademicYear::factory()->create();

    expect($user->can('create', AcademicYear::class))->toBeTrue();
    expect($user->can('update', $academicYear))->toBeTrue();
    expect($user->can('close', $academicYear))->toBeTrue();
});

it('denies create/update/close to a user without academic.manage-catalog', function () {
    $user = userWithAcademicCatalogPermission(grant: false);
    $academicYear = AcademicYear::factory()->create();

    expect($user->can('create', AcademicYear::class))->toBeFalse();
    expect($user->can('update', $academicYear))->toBeFalse();
    expect($user->can('close', $academicYear))->toBeFalse();
});

it('grants via the Super Admin Gate::before bypass regardless of permission', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $academicYear = AcademicYear::factory()->create();

    expect($superAdmin->can('close', $academicYear))->toBeTrue();
});
