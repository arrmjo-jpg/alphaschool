<?php

namespace App\Modules\Administration\Exceptions;

use RuntimeException;

/**
 * docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 8: raised when a write's expectedVersion does not match the
 * current row's version -- optimistic locking, never a silent
 * last-write-wins overwrite. The caller must re-resolve() and retry
 * with the fresh version, not blindly resubmit.
 */
class ConfigurationWriteConflictException extends RuntimeException
{
    public function __construct(string $key, int $expectedVersion, int $actualVersion)
    {
        parent::__construct(
            "Configuration write conflict on '{$key}': expected version {$expectedVersion}, but the current version is {$actualVersion} -- someone else changed this value first. Re-resolve and retry.",
        );
    }
}
