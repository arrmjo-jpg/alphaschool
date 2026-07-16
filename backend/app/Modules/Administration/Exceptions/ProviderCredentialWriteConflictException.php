<?php

namespace App\Modules\Administration\Exceptions;

use RuntimeException;

/**
 * docs/adr/0019-integration-platform-architecture.md Decision 5, reusing
 * ADR-0018 Decision 8's write-contract algorithm: raised when a
 * credential write's expectedVersion does not match the current row's
 * version. A distinct class from ConfigurationWriteConflictException,
 * not a shared base -- Blueprint Addendum B1's promotion-not-prediction
 * rule again (this is only the second real instance of the write-
 * contract shape, not yet a third consumer that would justify sharing).
 */
class ProviderCredentialWriteConflictException extends RuntimeException
{
    public function __construct(string $slotKey, int $expectedVersion, int $actualVersion)
    {
        parent::__construct(
            "Provider credential write conflict on '{$slotKey}': expected version {$expectedVersion}, but the current version is {$actualVersion} -- someone else changed this credential first. Re-resolve and retry.",
        );
    }
}
