<?php

namespace App\Core\ValueObjects;

/**
 * One entry in a SettingsResolver::resolve() trace
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 3, docs/adr/0021-administration-experience-layer.md Decision
 * 4: "the trace is a first-class artifact, not an internal detail") --
 * the ordered record of every altitude checked and whether each had a
 * row, mirroring the Assignment pattern's asOf(date) idiom
 * (Blueprint §6).
 */
final class AltitudeCheck
{
    public function __construct(
        public readonly string $altitude,
        public readonly bool $hadValue,
    ) {}
}
