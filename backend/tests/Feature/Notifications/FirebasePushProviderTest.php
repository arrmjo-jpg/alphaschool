<?php

use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Administration\Services\ProviderRegistry;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Providers\FirebasePushProvider;
use Illuminate\Support\Facades\Http;

/**
 * Proof provider #3 of 3 (Playbook Phase 2). An FCM v1 service-account
 * credential shape (project_id/client_email/private_key) -- a
 * multi-line PEM string, the strongest proof that the Vault's encrypted
 * column round-trips an arbitrary payload shape, not just short tokens
 * like the sibling proof providers'.
 */
beforeEach(function () {
    config(['administration.registered_provider_slots' => [FirebasePushProvider::class]]);
    app(ProviderRegistry::class)->sync();
    Http::preventStrayRequests();
});

function firebaseProviderEditor(): User
{
    $branch = Branch::factory()->create();
    $group = PermissionGroup::firstOrCreate(['code' => 'test-group'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'notifications.manage-push-provider', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );

    $user = User::factory()->create();
    withTeam($branch->id);
    $role = Role::create(['name' => 'firebase-editor-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    return $user->fresh();
}

function fakePemPrivateKey(): string
{
    return "-----BEGIN PRIVATE KEY-----\nMIIFakeKeyMaterialForTestingOnlyNotARealKey==\n-----END PRIVATE KEY-----\n";
}

it('fails health check and refuses to push before any credential is configured', function () {
    $provider = app(FirebasePushProvider::class);

    expect($provider->healthCheck())->toBeFalse()
        ->and($provider->sendPush('device-token-abc', 'Grade Posted', 'Your grade is ready'))->toBeFalse();
});

it('sends a push notification through the FCM v1 endpoint once real credentials -- including a PEM private key -- round-trip through the Vault', function () {
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/alphaschool-test/messages/0:abc'], 200),
    ]);

    app(ProviderCredentialVault::class)->write(
        'notifications.push.firebase',
        [
            'project_id' => 'alphaschool-test',
            'client_email' => 'firebase-adminsdk@alphaschool-test.iam.gserviceaccount.com',
            'private_key' => fakePemPrivateKey(),
        ],
        ConfigurationScopeContext::global(),
        0,
        firebaseProviderEditor(),
    );

    $provider = app(FirebasePushProvider::class);

    expect($provider->healthCheck())->toBeTrue()
        ->and($provider->sendPush('device-token-abc', 'Grade Posted', 'Your grade is ready'))->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/alphaschool-test/messages:send'
        && $request['message']['token'] === 'device-token-abc');

    // The exact PEM payload the credential shape most stress-tests --
    // proves the encrypted:array cast round-trips a multi-line string
    // byte-for-byte, not just short scalar tokens.
    $resolved = app(ProviderCredentialVault::class)->resolve('notifications.push.firebase', ConfigurationScopeContext::global());
    expect($resolved->credentials['private_key'])->toBe(fakePemPrivateKey());
});
