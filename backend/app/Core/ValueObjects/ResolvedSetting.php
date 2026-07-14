<?php

namespace App\Core\ValueObjects;

/**
 * The return shape of
 * App\Modules\Administration\Services\SettingsResolver::resolve()
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 3) -- never a bare value. `version` is the optimistic-locking
 * token (Decision 8, added by amendment) a subsequent write() call must
 * supply as its expectedVersion.
 *
 * @param  AltitudeCheck[]  $trace
 */
final class ResolvedSetting
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly ?string $resolvedAtAltitude,
        public readonly array $trace,
        public readonly int $version,
    ) {}
}
