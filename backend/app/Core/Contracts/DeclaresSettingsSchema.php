<?php

namespace App\Core\Contracts;

use App\Core\ValueObjects\SettingDefinition;

/**
 * Every module that wants a value resolved through the Configuration
 * Platform implements this and declares it into
 * App\Modules\Administration\Services\ConfigurationRegistry's manifest
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 2) -- a deploy-time, code-reviewed declaration, never
 * runtime-mutable. Administration Platform never invents a key's
 * meaning; it only stores and resolves what the owning module declares
 * here (docs/adr/0016 §1's "administers, never re-implements").
 */
interface DeclaresSettingsSchema
{
    /**
     * @return SettingDefinition[]
     */
    public static function settingsSchema(): array;
}
