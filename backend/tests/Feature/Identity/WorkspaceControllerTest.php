<?php

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase E-B's first real entry into GET /api/v1/workspaces
 * (docs/ADMIN_DESIGN_SYSTEM.md §26.13) -- previously always returned
 * [] unconditionally (ADR-0015 Decision 5's own zero-is-correct
 * baseline); this proves the real, permission-computed replacement.
 */
it('returns configuration-platform for a user holding identity.view-otp-settings', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);

    $group = PermissionGroup::firstOrCreate(['code' => 'identity-otp-test'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'identity.view-otp-settings', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );
    $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    $response = $this->actingAs($user->fresh())->getJson(route('workspaces.index'));

    $response->assertOk()->assertJson([
        'workspaces' => [['key' => 'configuration-platform', 'required_permission' => 'identity.view-otp-settings']],
    ]);
});

it('returns an empty list for a user without the permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('workspaces.index'));

    $response->assertOk()->assertJson(['workspaces' => []]);
});

it('returns configuration-platform and provider-registry for is_super_admin regardless of granted permissions', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($superAdmin)->getJson(route('workspaces.index'));

    $response->assertOk()->assertJson([
        'workspaces' => [
            ['key' => 'configuration-platform', 'required_permission' => 'identity.view-otp-settings'],
            ['key' => 'provider-registry', 'required_permission' => 'administration.providers.view'],
        ],
    ]);
});

/**
 * Phase F-B's second real entry (docs/ADMIN_DESIGN_SYSTEM.md §27.6) --
 * a dedicated workspace-visibility permission, deliberately not the
 * union of the four per-slot edit permissions.
 */
it('returns provider-registry for a user holding administration.providers.view', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);

    $group = PermissionGroup::firstOrCreate(['code' => 'administration-test'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'administration.providers.view', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );
    $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    $response = $this->actingAs($user->fresh())->getJson(route('workspaces.index'));

    $response->assertOk()->assertJson([
        'workspaces' => [['key' => 'provider-registry', 'required_permission' => 'administration.providers.view']],
    ]);
});

it('does not grant provider-registry visibility merely from holding a per-slot edit permission', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);

    $group = PermissionGroup::firstOrCreate(['code' => 'administration-test'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'identity.manage-oauth-provider', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );
    $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    $response = $this->actingAs($user->fresh())->getJson(route('workspaces.index'));

    $response->assertOk()->assertJson(['workspaces' => []]);
});
