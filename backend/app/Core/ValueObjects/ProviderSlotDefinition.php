<?php

namespace App\Core\ValueObjects;

/**
 * The Provider Registry's own registration shape
 * (docs/adr/0019-integration-platform-architecture.md Decision 1) --
 * scaffolded now, per this Playbook's own Phase 1 Developer Enablement
 * scope, so Phase 2 consumes an already-agreed contract rather than
 * inventing one under its own deadline. No Vault, credential-storage,
 * or health-check-runner logic exists yet -- that is Phase 2's actual
 * implementation. This is the shape only: a capability contract per
 * vendor category, the credential fields it requires, and the
 * health-check callback's signature.
 */
final class ProviderSlotDefinition
{
    /**
     * @param  string[]  $credentialFields  e.g. ["api_key", "api_secret", "region"] -- which fields the Vault encrypts, never the values themselves.
     * @param  callable(): bool  $healthCheck  Synchronous in Phase 2's first implementation (ADR-0022's own delivery-principle note); async/scheduled is a later refinement, not a Phase 1 decision.
     */
    public function __construct(
        public readonly string $slotKey,
        public readonly string $capabilityContract,
        public readonly array $credentialFields,
        public readonly string $owningModule,
        public readonly mixed $healthCheck = null,
    ) {}
}
