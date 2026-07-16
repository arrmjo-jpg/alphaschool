<?php

use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Modules\Administration\Models\ProviderRegistration;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Administration\Services\ProviderRegistry;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Media\Providers\R2StorageProvider;

/**
 * Playbook Phase 2's mandated retrofit target: "Media's existing disk-
 * tier selection as the first real Provider." Proves R2StorageProvider
 * round-trips real key/secret/region/endpoint credentials through the
 * Vault -- the actual feeding of config/filesystems.php's disk arrays
 * happens in App\Providers\AppServiceProvider::configureMediaStorageCredentials(),
 * which is only reachable when a disk's driver is 's3' (this test suite
 * runs on the 'local' driver, so that boot()-time path is exercised as
 * a documented no-op here -- see its own guard clause).
 */
beforeEach(function () {
    config(['administration.registered_provider_slots' => [R2StorageProvider::class]]);
    app(ProviderRegistry::class)->sync();
});

function r2ProviderEditor(): User
{
    $branch = Branch::factory()->create();
    $group = PermissionGroup::firstOrCreate(['code' => 'test-group'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'media.manage-storage-provider', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );

    $user = User::factory()->create();
    withTeam($branch->id);
    $role = Role::create(['name' => 'r2-editor-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    return $user->fresh();
}

it('fails health check before any R2 credential is configured', function () {
    expect(app(R2StorageProvider::class)->healthCheck())->toBeFalse();
});

it('resolves real R2 credentials through the Vault once written, and passes health check', function () {
    app(ProviderCredentialVault::class)->write(
        'media.storage.r2',
        ['key' => 'r2-access-key', 'secret' => 'r2-secret-key', 'region' => 'auto', 'endpoint' => 'https://accountid.r2.cloudflarestorage.com'],
        ConfigurationScopeContext::global(),
        0,
        r2ProviderEditor(),
    );

    $provider = app(R2StorageProvider::class);

    expect($provider->healthCheck())->toBeTrue()
        ->and($provider->resolveCredentials())->toBe([
            'key' => 'r2-access-key',
            'secret' => 'r2-secret-key',
            'region' => 'auto',
            'endpoint' => 'https://accountid.r2.cloudflarestorage.com',
        ]);
});

it('registers with a credential shape genuinely different from the other two proof providers', function () {
    $registration = ProviderRegistration::where('slot_key', 'media.storage.r2')->first();

    expect($registration->credential_fields)->toBe(['key', 'secret', 'region', 'endpoint']);
});
