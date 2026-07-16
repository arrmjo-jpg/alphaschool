<?php

namespace App\Modules\Administration\Services;

use App\Modules\Administration\Models\ProviderRegistration;
use InvalidArgumentException;

/**
 * The Manager-pattern resolution mechanism ADR-0019 Decision 1 names
 * ("resolved via the same Manager-pattern mechanism ADR-0013 already
 * specified") -- table-driven only: looks up a slot's provider_class in
 * the Registry and resolves it through the container. No vendor name
 * ever appears in a switch/match/if-chain here or anywhere else in this
 * class; that is the concrete mechanism behind Playbook Phase 2's
 * "provider discovery works entirely through the registry."
 *
 * A resolved Provider is expected to pull its own credentials lazily
 * (constructor-injecting App\Modules\Administration\Services\
 * ProviderCredentialVault and calling resolve() when it actually needs
 * them) -- this class never touches a credential value itself.
 */
class ProviderManager
{
    public function resolve(string $slotKey): object
    {
        $registration = ProviderRegistration::where('slot_key', $slotKey)->first();

        if ($registration === null) {
            throw new InvalidArgumentException("ProviderManager: unknown Provider slot '{$slotKey}' -- not registered. Run `php artisan administration:sync-providers` after declaring it.");
        }

        return app($registration->provider_class);
    }
}
