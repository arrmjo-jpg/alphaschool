<?php

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Employee;

/**
 * UI Sprint 2's minimal Employee lookup (docs/ADMIN_DESIGN_SYSTEM.md
 * §30.5) -- HomeroomAssignment's Create form is the first real
 * consumer.
 */
function userWithCatalogPermissionForEmployeeLookup(bool $granted): User
{
    $branch = Branch::factory()->create();
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

it('lists only active employees, with real names resolved via Person', function () {
    Employee::factory()->create();
    Employee::factory()->inactive()->create();
    $user = userWithCatalogPermissionForEmployeeLookup(true);

    $response = $this->actingAs($user)->getJson(route('employees.index'));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0])->toHaveKeys(['id', 'name_en', 'name_ar'])
        ->and($data[0]['name_en'])->not->toBeEmpty();
});

it('denies listing for a user without the permission', function () {
    $user = userWithCatalogPermissionForEmployeeLookup(false);

    $this->actingAs($user)->getJson(route('employees.index'))->assertForbidden();
});

it('allows a super admin regardless of granted permissions', function () {
    Employee::factory()->create();
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($superAdmin)->getJson(route('employees.index'))->assertOk();
});
