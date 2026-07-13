<?php

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Person;

/**
 * The two backend endpoints ADR-0015 / docs/ADMIN_PLATFORM.md require
 * before the Admin Platform Foundation shell can be built: current
 * user + permissions, and workspace access (empty until a real
 * workspace ships).
 */
it('returns the current user and their permission set across all branches', function () {
    $person = Person::factory()->create();
    $user = User::factory()->create(['person_id' => $person->id]);

    $branchOne = Branch::factory()->create();
    $branchTwo = Branch::factory()->create();
    $group = PermissionGroup::firstOrCreate(['code' => 'test-group'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permissionOne = Permission::firstOrCreate(['name' => 'test.permission-one', 'guard_name' => 'sanctum'], ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']]);
    $permissionTwo = Permission::firstOrCreate(['name' => 'test.permission-two', 'guard_name' => 'sanctum'], ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']]);

    withTeam($branchOne->id);
    $roleOne = Role::create(['name' => 'branch-one-role', 'guard_name' => 'sanctum', 'branch_id' => $branchOne->id]);
    $roleOne->givePermissionTo($permissionOne);
    $user->assignRole($roleOne);

    withTeam($branchTwo->id);
    $roleTwo = Role::create(['name' => 'branch-two-role', 'guard_name' => 'sanctum', 'branch_id' => $branchTwo->id]);
    $roleTwo->givePermissionTo($permissionTwo);
    $user->assignRole($roleTwo);

    $response = $this->actingAs($user->fresh())->getJson(route('me'));

    $response->assertOk()
        ->assertJsonPath('user.username', $user->username)
        ->assertJsonPath('user.name.en', $person->name()->fullNameEn());

    expect($response->json('permissions'))
        ->toContain('test.permission-one')
        ->toContain('test.permission-two');
});

it('rejects an unauthenticated request to /me', function () {
    $this->getJson(route('me'))->assertUnauthorized();
});

it('returns an empty workspace list -- nothing is registered yet', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('workspaces.index'));

    $response->assertOk()->assertExactJson(['workspaces' => []]);
});

it('rejects an unauthenticated request to /workspaces', function () {
    $this->getJson(route('workspaces.index'))->assertUnauthorized();
});
