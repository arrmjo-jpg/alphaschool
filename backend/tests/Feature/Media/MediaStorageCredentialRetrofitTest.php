<?php

use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Administration\Services\ProviderRegistry;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Media\Providers\R2StorageProvider;
use App\Providers\AppServiceProvider;

/**
 * Proves the actual retrofit, not just R2StorageProvider's own
 * credential round-trip (see R2StorageProviderTest for that):
 * App\Providers\AppServiceProvider::configureMediaStorageCredentials()
 * genuinely replaces config/filesystems.php's env()-sourced R2
 * key/secret/region/endpoint with the Vault's resolved values, for any
 * disk tier whose driver is 's3' -- and genuinely leaves a 'local'-
 * driven tier (this suite's own default) untouched, proving the guard
 * clause is real, not merely present.
 */
beforeEach(function () {
    config(['administration.registered_provider_slots' => [R2StorageProvider::class]]);
    app(ProviderRegistry::class)->sync();
});

function r2RetrofitEditor(): User
{
    $branch = Branch::factory()->create();
    $group = PermissionGroup::firstOrCreate(['code' => 'test-group'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'media.manage-storage-provider', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );

    $user = User::factory()->create();
    withTeam($branch->id);
    $role = Role::create(['name' => 'r2-retrofit-editor-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    return $user->fresh();
}

it('leaves a local-driven disk tier untouched -- the retrofit only ever activates for an s3-driven tier', function () {
    expect(config('filesystems.disks.public.driver'))->toBe('local');

    app(ProviderCredentialVault::class)->write(
        'media.storage.r2',
        ['key' => 'should-not-apply', 'secret' => 'should-not-apply', 'region' => 'auto', 'endpoint' => 'https://example.r2.cloudflarestorage.com'],
        ConfigurationScopeContext::global(),
        0,
        r2RetrofitEditor(),
    );

    (new AppServiceProvider(app()))->boot();

    expect(config('filesystems.disks.public.key'))->not->toBe('should-not-apply');
});

it('injects the Vault-resolved R2 credentials into every s3-driven disk tier at boot time', function () {
    config([
        'filesystems.disks.public.driver' => 's3',
        'filesystems.disks.private.driver' => 's3',
        'filesystems.disks.temporary.driver' => 'local',
    ]);

    app(ProviderCredentialVault::class)->write(
        'media.storage.r2',
        ['key' => 'retrofit-key', 'secret' => 'retrofit-secret', 'region' => 'auto', 'endpoint' => 'https://example.r2.cloudflarestorage.com'],
        ConfigurationScopeContext::global(),
        0,
        r2RetrofitEditor(),
    );

    (new AppServiceProvider(app()))->boot();

    expect(config('filesystems.disks.public.key'))->toBe('retrofit-key')
        ->and(config('filesystems.disks.public.secret'))->toBe('retrofit-secret')
        ->and(config('filesystems.disks.private.key'))->toBe('retrofit-key')
        ->and(config('filesystems.disks.temporary.key'))->toBeNull();
});
