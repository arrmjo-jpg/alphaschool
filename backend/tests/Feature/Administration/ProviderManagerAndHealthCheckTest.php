<?php

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\Contracts\HealthCheckable;
use App\Core\ValueObjects\ProviderCredentialFieldDefinition;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Models\ProviderRegistration;
use App\Modules\Administration\Services\HealthCheckRunner;
use App\Modules\Administration\Services\ProviderManager;
use App\Modules\Administration\Services\ProviderRegistry;
use App\Modules\Identity\Providers\GoogleOAuthProvider;
use App\Modules\Media\Providers\R2StorageProvider;
use App\Modules\Notifications\Providers\FirebasePushProvider;
use App\Modules\Notifications\Providers\SmtpEmailProvider;

/**
 * Proves Playbook Phase 2's discovery/genericity requirements directly:
 * "provider discovery works entirely through the registry and never
 * through manual conditionals" and "a provider can be added without
 * modifying existing providers."
 */
class HealthyFixtureProvider implements DeclaresProviderSlots, HealthCheckable
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.healthy-fixture',
                capabilityContract: 'test.category',
                credentialFields: [new ProviderCredentialFieldDefinition('token', 'text')],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
        ];
    }

    public function healthCheck(): bool
    {
        return true;
    }
}

class UnhealthyFixtureProvider implements DeclaresProviderSlots, HealthCheckable
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.unhealthy-fixture',
                capabilityContract: 'test.category',
                credentialFields: [new ProviderCredentialFieldDefinition('token', 'text')],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
        ];
    }

    public function healthCheck(): bool
    {
        return false;
    }
}

class NotCheckableFixtureProvider implements DeclaresProviderSlots
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.not-checkable-fixture',
                capabilityContract: 'test.category',
                credentialFields: [new ProviderCredentialFieldDefinition('token', 'text')],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
        ];
    }
}

it('registers all four real Playbook Phase 2 providers together, each with a genuinely different credential shape', function () {
    config(['administration.registered_provider_slots' => [
        R2StorageProvider::class,
        SmtpEmailProvider::class,
        GoogleOAuthProvider::class,
        FirebasePushProvider::class,
    ]]);

    $result = app(ProviderRegistry::class)->sync();

    expect($result['synced'])->toHaveCount(4);

    $shapes = ProviderRegistration::whereIn('slot_key', $result['synced'])
        ->pluck('credential_fields', 'slot_key')
        ->toArray();

    // Every shape is genuinely different -- proving the Registry and
    // Vault impose no assumed credential structure (Playbook Phase 2:
    // "prove that the Provider Registry is genuinely generic, not merely
    // capable of handling multiple SMTP-like providers"). credential_fields
    // is now [{name, type}, ...] (§27.4/§27.5), so only the names are
    // compared -- two slots sharing a field name but different types
    // would still count as "the same shape" here, which is correct: this
    // assertion is about structural genericity, not about type coverage.
    $uniqueShapes = array_unique(array_map(
        fn ($fields) => implode(',', array_column($fields, 'name')),
        $shapes,
    ));
    expect($uniqueShapes)->toHaveCount(4);
});

it('resolves a provider through the container purely from the Registry row -- adding a new provider requires zero changes to ProviderManager', function () {
    config(['administration.registered_provider_slots' => [
        SmtpEmailProvider::class,
        HealthyFixtureProvider::class,
    ]]);
    app(ProviderRegistry::class)->sync();

    $smtp = app(ProviderManager::class)->resolve('notifications.email.smtp');
    $fixture = app(ProviderManager::class)->resolve('test.healthy-fixture');

    expect($smtp)->toBeInstanceOf(SmtpEmailProvider::class)
        ->and($fixture)->toBeInstanceOf(HealthyFixtureProvider::class);
});

it('reports healthy when the resolved provider genuinely passes its own health check', function () {
    config(['administration.registered_provider_slots' => [HealthyFixtureProvider::class]]);
    app(ProviderRegistry::class)->sync();

    $result = app(HealthCheckRunner::class)->check('test.healthy-fixture');

    expect($result['status'])->toBe('healthy')->and($result['cached'])->toBeFalse();
});

it('reports unhealthy when the resolved provider genuinely fails its own health check', function () {
    config(['administration.registered_provider_slots' => [UnhealthyFixtureProvider::class]]);
    app(ProviderRegistry::class)->sync();

    $result = app(HealthCheckRunner::class)->check('test.unhealthy-fixture');

    expect($result['status'])->toBe('unhealthy');
});

it('reports not_checkable for a provider that does not implement HealthCheckable, rather than failing', function () {
    config(['administration.registered_provider_slots' => [NotCheckableFixtureProvider::class]]);
    app(ProviderRegistry::class)->sync();

    $result = app(HealthCheckRunner::class)->check('test.not-checkable-fixture');

    expect($result['status'])->toBe('not_checkable');
});

it('caches a health-check result for the short TTL rather than re-invoking the provider on every call', function () {
    config(['administration.registered_provider_slots' => [HealthyFixtureProvider::class]]);
    app(ProviderRegistry::class)->sync();

    $first = app(HealthCheckRunner::class)->check('test.healthy-fixture');
    $second = app(HealthCheckRunner::class)->check('test.healthy-fixture');

    expect($first['cached'])->toBeFalse()
        ->and($second['cached'])->toBeTrue()
        ->and($second['status'])->toBe('healthy');
});
