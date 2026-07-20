<?php

use App\Modules\Administration\Models\ConfigurationValue;
use App\Modules\Administration\Services\ConfigurationRegistry;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\IdentityOtpSettings;

/**
 * Phase E-B's adapter surface (docs/ADMIN_DESIGN_SYSTEM.md §26.13) --
 * verified as a real HTTP Feature test, not raw curl against a running
 * server, specifically because Spatie's team context
 * (`setPermissionsTeamId()`) only ever persists within one PHP process
 * lifecycle (a real curl request to `php artisan serve` spawns a fresh
 * process per request and never establishes it) -- a real, load-bearing
 * finding from manual verification, not assumed.
 */
function userWithOtpPermission(array $permissions, ?Branch $branch = null): User
{
    $branch ??= Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);

    if ($permissions !== []) {
        $group = PermissionGroup::firstOrCreate(['code' => 'identity-otp-test'], ['name' => ['en' => 'x', 'ar' => 'y']]);
        $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);

        foreach ($permissions as $permission) {
            $permissionModel = Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['permission_group_id' => $group->id, 'display_name' => ['en' => $permission, 'ar' => $permission]],
            );
            $role->givePermissionTo($permissionModel);
        }

        $user->assignRole($role);
    }

    return $user->fresh();
}

beforeEach(function () {
    config(['administration.registered_settings_schemas' => [IdentityOtpSettings::class]]);
    app(ConfigurationRegistry::class)->sync();
});

it('lists the access-governance category for a user with view permission', function () {
    $viewer = userWithOtpPermission(['identity.view-otp-settings']);

    $response = $this->actingAs($viewer)->getJson(route('administration.configuration.categories'));

    $response->assertOk()->assertJson(['categories' => [['key' => 'access-governance', 'status' => 'ready']]]);
});

it('omits categories the user has no view permission for', function () {
    $noPermission = userWithOtpPermission([]);

    $response = $this->actingAs($noPermission)->getJson(route('administration.configuration.categories'));

    $response->assertOk()->assertJson(['categories' => []]);
});

it('includes the category for is_super_admin regardless of granted permissions', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($superAdmin)->getJson(route('administration.configuration.categories'));

    $response->assertOk()->assertJson(['categories' => [['key' => 'access-governance', 'status' => 'ready']]]);
});

it('resolves category settings to their declared defaults with canEdit false when the user lacks edit permission', function () {
    $viewer = userWithOtpPermission(['identity.view-otp-settings']);

    $response = $this->actingAs($viewer)->getJson(route('administration.configuration.categories.settings', ['capability' => 'access-governance']));

    $response->assertOk();
    $settings = collect($response->json('settings'))->keyBy('key');

    expect($settings['identity.otp.code_length']['value'])->toBe(6)
        ->and($settings['identity.otp.code_length']['resolvedFrom'])->toBe('default')
        ->and($settings['identity.otp.code_length']['canEdit'])->toBeFalse()
        ->and($settings['identity.otp.code_length']['version'])->toBe(0);
});

it('marks canEdit true only for a user actually holding the edit permission', function () {
    // Also needs view permission -- editing a field you cannot see at
    // all does not make sense, and hasViewPermission() is a separate,
    // real gate this endpoint enforces before canEdit is even computed.
    $editor = userWithOtpPermission(['identity.view-otp-settings', 'identity.configure-otp-settings']);

    $response = $this->actingAs($editor)->getJson(route('administration.configuration.categories.settings', ['capability' => 'access-governance']));

    $settings = collect($response->json('settings'))->keyBy('key');
    expect($settings['identity.otp.code_length']['canEdit'])->toBeTrue();
});

it('never marks canEdit true for is_super_admin -- the write endpoint has no such bypass', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($superAdmin)->getJson(route('administration.configuration.categories.settings', ['capability' => 'access-governance']));

    $settings = collect($response->json('settings'))->keyBy('key');
    expect($settings['identity.otp.code_length']['canEdit'])->toBeFalse();
});

it('writes a setting when the user holds the edit permission, at the correct expectedVersion', function () {
    $editor = userWithOtpPermission(['identity.configure-otp-settings']);

    $response = $this->actingAs($editor)->patchJson(
        route('administration.configuration.categories.settings.write', ['capability' => 'access-governance', 'key' => 'identity.otp.code_length']),
        ['value' => 8, 'expectedVersion' => 0],
    );

    $response->assertOk()->assertJson(['key' => 'identity.otp.code_length', 'value' => 8, 'version' => 1, 'status' => 'active']);
    expect(ConfigurationValue::where('configuration_key', 'identity.otp.code_length')->where('status', ConfigurationValue::STATUS_ACTIVE)->first()->value)->toBe(8);
});

it('rejects a write from a user lacking the edit permission with 403, even is_super_admin', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($superAdmin)->patchJson(
        route('administration.configuration.categories.settings.write', ['capability' => 'access-governance', 'key' => 'identity.otp.code_length']),
        ['value' => 8, 'expectedVersion' => 0],
    );

    $response->assertStatus(403);
});

it('rejects a write with a stale expectedVersion as a 409 carrying the real currentVersion', function () {
    $editor = userWithOtpPermission(['identity.configure-otp-settings']);

    $this->actingAs($editor)->patchJson(
        route('administration.configuration.categories.settings.write', ['capability' => 'access-governance', 'key' => 'identity.otp.code_length']),
        ['value' => 8, 'expectedVersion' => 0],
    )->assertOk();

    $response = $this->actingAs($editor)->patchJson(
        route('administration.configuration.categories.settings.write', ['capability' => 'access-governance', 'key' => 'identity.otp.code_length']),
        ['value' => 9, 'expectedVersion' => 0],
    );

    $response->assertStatus(409)->assertJson(['currentVersion' => 1]);
});

it('rejects a write violating the declared validation rules as a 422', function () {
    $editor = userWithOtpPermission(['identity.configure-otp-settings']);

    $response = $this->actingAs($editor)->patchJson(
        route('administration.configuration.categories.settings.write', ['capability' => 'access-governance', 'key' => 'identity.otp.code_length']),
        ['value' => 999, 'expectedVersion' => 0],
    );

    $response->assertStatus(422);
});
