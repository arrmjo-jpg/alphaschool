<?php

namespace App\Core\Contracts;

use App\Core\ValueObjects\ProviderSlotDefinition;

/**
 * The Provider Registry's registration contract
 * (docs/adr/0019-integration-platform-architecture.md Decision 1) --
 * scaffolded in Phase 1 as Developer Enablement (Provider SDK), real
 * implementations arrive in Phase 2
 * (docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md). Mirrors
 * DeclaresSettingsSchema's shape deliberately: a module declares what
 * it needs, Administration Platform stores and administers it, never
 * inventing the meaning of a slot itself (docs/adr/0016 §1).
 */
interface DeclaresProviderSlots
{
    /**
     * @return ProviderSlotDefinition[]
     */
    public static function providerSlots(): array;
}
