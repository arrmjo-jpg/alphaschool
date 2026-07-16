<?php

namespace App\Core\ValueObjects;

/**
 * The Provider Registry's own registration shape
 * (docs/adr/0019-integration-platform-architecture.md Decision 1) --
 * scaffolded in Phase 1, per that Playbook's own Developer Enablement
 * scope, so Phase 2 consumes an already-agreed contract rather than
 * inventing one under its own deadline. This is the shape only: a
 * capability contract per vendor category, the credential fields it
 * requires, and the health-check callback's signature.
 *
 * $requiredPermissionToEdit/$approvalRequired/$approvalPermission were
 * added during Phase 2 implementation (2026-07-16), additively -- the
 * Phase 1 scaffold predated any real Vault write path and had nothing
 * to gate yet. Playbook Phase 2's own stated security line ("a distinct,
 * narrower permission gates [credentials] versus generic Configuration
 * access") and ADR-0022 §1 ("Provider credentials are ... approval-
 * gateable using the Configuration Platform's own mechanisms directly")
 * both require these fields to exist somewhere; mirroring
 * SettingDefinition's identical fields (ADR-0018 Decisions 9-10) here is
 * the smallest change that supplies them, not a reopening of Phase 1's
 * frozen decisions -- nothing in Phase 1 consumed this VO yet.
 */
final class ProviderSlotDefinition
{
    /**
     * @param  string[]  $credentialFields  e.g. ["api_key", "api_secret", "region"] -- which fields the Vault encrypts, never the values themselves.
     * @param  callable(): bool|null  $healthCheck  Synchronous in Phase 2's first implementation (ADR-0022's own delivery-principle note); async/scheduled is a later refinement. Optional -- a resolved Provider instance implementing App\Core\Contracts\HealthCheckable is the primary invocation path (it can be reflected on and persisted by slot_key; a raw closure cannot); this field exists for a slot whose health check is not naturally a method on the Provider itself.
     */
    public function __construct(
        public readonly string $slotKey,
        public readonly string $capabilityContract,
        public readonly array $credentialFields,
        public readonly string $owningModule,
        public readonly mixed $healthCheck = null,
        public readonly string $requiredPermissionToEdit = '',
        public readonly bool $approvalRequired = false,
        public readonly ?string $approvalPermission = null,
    ) {}
}
