<?php

use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Administration\Services\ProviderRegistry;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Providers\SmtpEmailProvider;
use Illuminate\Support\Facades\Mail;

/**
 * Proof provider #1 of 3 (Playbook Phase 2). Proves the full round-trip:
 * declare -> sync into the Registry -> write real credentials through
 * the Vault -> the Provider itself resolves and uses them, with zero
 * knowledge of the Vault's storage mechanism beyond calling resolve().
 */
beforeEach(function () {
    config(['administration.registered_provider_slots' => [SmtpEmailProvider::class]]);
    app(ProviderRegistry::class)->sync();
    Mail::fake();
});

function smtpProviderEditor(): User
{
    $branch = Branch::factory()->create();
    $group = PermissionGroup::firstOrCreate(['code' => 'test-group'], ['name' => ['en' => 'x', 'ar' => 'y']]);
    $permission = Permission::firstOrCreate(
        ['name' => 'notifications.manage-email-provider', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
    );

    $user = User::factory()->create();
    withTeam($branch->id);
    $role = Role::create(['name' => 'smtp-editor-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    return $user->fresh();
}

it('fails health check and refuses to send before any credential is configured', function () {
    $provider = app(SmtpEmailProvider::class);

    expect($provider->healthCheck())->toBeFalse()
        ->and($provider->send('student@example.com', 'Welcome', 'Hello'))->toBeFalse();
});

it('sends through the dynamically-configured SMTP mailer once real credentials are written to the Vault', function () {
    app(ProviderCredentialVault::class)->write(
        'notifications.email.smtp',
        ['host' => 'smtp.example.com', 'port' => 587, 'username' => 'no-reply@alphaschool.test', 'password' => 'app-password', 'encryption' => 'tls'],
        ConfigurationScopeContext::global(),
        0,
        smtpProviderEditor(),
    );

    $provider = app(SmtpEmailProvider::class);

    expect($provider->healthCheck())->toBeTrue()
        ->and($provider->send('student@example.com', 'Welcome', 'Hello'))->toBeTrue();

    Mail::assertSentCount(1);
});
