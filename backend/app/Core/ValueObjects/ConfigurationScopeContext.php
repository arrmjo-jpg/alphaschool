<?php

namespace App\Core\ValueObjects;

/**
 * The altitude a Configuration read/write resolves against
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 4). Platform/Deployment/Organization collapse into one
 * "global" scope in this dedicated-instance-per-customer commercial
 * model (ADR-0006) -- there is exactly one Organization per deployment,
 * so those three altitudes can never actually diverge in value, and
 * this codebase does not model a distinction it cannot exercise. Branch
 * is the one real override tier below global. User Preferences are
 * deliberately not represented here at all -- a separate, parallel,
 * lower-ceremony mechanism (Decision 4), never resolved through this
 * scope shape.
 */
final class ConfigurationScopeContext
{
    public function __construct(
        public readonly ?int $branchId = null,
    ) {}

    public static function global(): self
    {
        return new self(branchId: null);
    }

    public static function forBranch(int $branchId): self
    {
        return new self(branchId: $branchId);
    }

    public function altitude(): string
    {
        return $this->branchId === null ? 'global' : 'branch';
    }
}
