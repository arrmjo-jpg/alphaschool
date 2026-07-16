<?php

namespace App\Modules\Media\Providers;

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\Contracts\HealthCheckable;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Services\ProviderCredentialVault;

/**
 * Playbook Phase 2's mandated retrofit target: "Media's existing disk-
 * tier selection as the first real Provider." One shared R2 account
 * (key/secret/region/endpoint) backs all three Media disk tiers
 * (config/filesystems.php's public/private/temporary) -- only the
 * bucket name differs per tier, and a bucket name is not a secret, so
 * it stays a plain per-disk config value, not a Vault field.
 *
 * No custom capability-contract interface -- storage credential-feeding
 * has no behavioral polymorphism to abstract (Laravel's own Filesystem
 * Manager already handles driver dispatch); `capability_contract` here
 * is a descriptive string only. App\Modules\Administration\Services\
 * ProviderRegistry::assertCapabilityContractSatisfied() skips its
 * implements-check for any contract string that is not a real interface
 * -- this is that case, deliberately.
 */
class R2StorageProvider implements DeclaresProviderSlots, HealthCheckable
{
    public const SLOT_KEY = 'media.storage.r2';

    public function __construct(
        private readonly ProviderCredentialVault $vault,
    ) {}

    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: self::SLOT_KEY,
                capabilityContract: 'media.storage',
                credentialFields: ['key', 'secret', 'region', 'endpoint'],
                owningModule: 'Media',
                requiredPermissionToEdit: 'media.manage-storage-provider',
            ),
        ];
    }

    /**
     * @return array{key: string, secret: string, region: string, endpoint: string}|null
     */
    public function resolveCredentials(): ?array
    {
        return $this->vault->resolve(self::SLOT_KEY, ConfigurationScopeContext::global())->credentials;
    }

    public function healthCheck(): bool
    {
        $credentials = $this->resolveCredentials();

        if ($credentials === null) {
            return false;
        }

        return filled($credentials['key'] ?? null)
            && filled($credentials['secret'] ?? null)
            && filled($credentials['endpoint'] ?? null);
    }
}
