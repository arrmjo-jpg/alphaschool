<?php

namespace App\Core\ValueObjects;

/**
 * The return shape of App\Modules\Administration\Services\
 * ProviderCredentialVault::resolve() (docs/adr/0019-integration-platform
 * -architecture.md Decision 5) -- mirrors ResolvedSetting deliberately
 * (same trace-returning altitude-chain idiom), but never a bare array
 * either: `credentials` is the one place a decrypted secret payload is
 * allowed to exist as a plain PHP value, and only for the duration of
 * the calling Provider's own use of it -- never logged, cached, or
 * serialized by the caller.
 *
 * @param  AltitudeCheck[]  $trace
 */
final class ResolvedProviderCredential
{
    public function __construct(
        public readonly string $slotKey,
        public readonly ?array $credentials,
        public readonly ?string $resolvedAtAltitude,
        public readonly array $trace,
        public readonly int $version,
    ) {}
}
