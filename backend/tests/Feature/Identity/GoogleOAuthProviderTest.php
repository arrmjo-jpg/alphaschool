<?php

use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Administration\Services\ProviderRegistry;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Providers\GoogleOAuthProvider;
use Illuminate\Support\Facades\Http;

/**
 * Proof provider #2 of 3 (Playbook Phase 2). A client_id/client_secret
 * credential shape -- deliberately different from SmtpEmailProvider's
 * five-field quintet.
 */
beforeEach(function () {
    config(['administration.registered_provider_slots' => [GoogleOAuthProvider::class]]);
    app(ProviderRegistry::class)->sync();
    Http::preventStrayRequests();
});

function oauthProviderEditor(): User
{
    $branch = Branch::factory()->create();
    $group = PermissionGroup::firstOrCreate(['code' => 'test-group'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'identity.manage-oauth-provider', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );

    $user = User::factory()->create();
    withTeam($branch->id);
    $role = Role::create(['name' => 'oauth-editor-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    return $user->fresh();
}

it('fails health check before any credential is configured', function () {
    expect(app(GoogleOAuthProvider::class)->healthCheck())->toBeFalse();
});

it('refuses to build an authorization URL or exchange a code before credentials exist', function () {
    expect(fn () => app(GoogleOAuthProvider::class)->getAuthorizationUrl('https://app.test/callback', ['openid', 'email']))
        ->toThrow(RuntimeException::class);
});

it('builds a real authorization URL and exchanges a code for a token once credentials are written to the Vault', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600], 200),
    ]);

    app(ProviderCredentialVault::class)->write(
        'identity.federation.google-oauth',
        ['client_id' => 'client-123.apps.googleusercontent.com', 'client_secret' => 'shh-its-a-secret'],
        ConfigurationScopeContext::global(),
        0,
        oauthProviderEditor(),
    );

    $provider = app(GoogleOAuthProvider::class);

    expect($provider->healthCheck())->toBeTrue();

    $url = $provider->getAuthorizationUrl('https://app.test/callback', ['openid', 'email']);
    expect($url)->toContain('client-123.apps.googleusercontent.com')
        ->and($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth');

    $token = $provider->exchangeCodeForToken('auth-code', 'https://app.test/callback');
    expect($token['access_token'])->toBe('fake-access-token');

    Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['client_secret'] === 'shh-its-a-secret');
});
