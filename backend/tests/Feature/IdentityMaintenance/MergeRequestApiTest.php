<?php

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\IdentityMaintenance\Models\MergeRequest;
use App\Modules\People\Models\Person;

function requesterWithPermission(): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);
    $group = PermissionGroup::firstOrCreate(['code' => 'identity-governance'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'identity.request-merge', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );
    $role = Role::create(['name' => 'api-test-requester', 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    return $user->fresh();
}

it('creates a merge request via the API when authorized', function () {
    $losing = Person::factory()->create();
    $winning = Person::factory()->create();
    $requester = requesterWithPermission();

    $response = $this->actingAs($requester)->postJson(route('merge-requests.store'), [
        'losing_person_id' => $losing->id,
        'winning_person_id' => $winning->id,
    ]);

    $response->assertCreated();
    expect(MergeRequest::where('losing_person_id', $losing->id)->exists())->toBeTrue();
});

it('rejects creating a merge request via the API without the identity.request-merge permission', function () {
    // Permission exists system-wide, just not granted to this user --
    // matches Sprint 3.1's own finding that hasPermissionTo() throws
    // PermissionDoesNotExist (not a clean false) for a genuinely unseeded
    // permission, which would otherwise surface as a 500, not a 403.
    $group = PermissionGroup::firstOrCreate(['code' => 'identity-governance'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    Permission::firstOrCreate(
        ['name' => 'identity.request-merge', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );

    $losing = Person::factory()->create();
    $winning = Person::factory()->create();
    $unprivileged = User::factory()->create();

    $response = $this->actingAs($unprivileged)->postJson(route('merge-requests.store'), [
        'losing_person_id' => $losing->id,
        'winning_person_id' => $winning->id,
    ]);

    $response->assertForbidden();
});

it('rejects creating a merge request where losing and winning are the same person', function () {
    $person = Person::factory()->create();
    $requester = requesterWithPermission();

    $response = $this->actingAs($requester)->postJson(route('merge-requests.store'), [
        'losing_person_id' => $person->id,
        'winning_person_id' => $person->id,
    ]);

    $response->assertUnprocessable();
});

it('rejects an unauthenticated request entirely', function () {
    $response = $this->postJson(route('merge-requests.store'), []);

    $response->assertUnauthorized();
});
