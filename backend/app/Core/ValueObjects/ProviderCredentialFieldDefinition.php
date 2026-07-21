<?php

namespace App\Core\ValueObjects;

/**
 * One credential field's shape within a Provider slot's declaration
 * (docs/ADMIN_DESIGN_SYSTEM.md §27.4/§27.5 pre-freeze amendment) --
 * introduced specifically to close a real gap the Administration
 * Workspace's frontend would otherwise have had no honest way to fill:
 * a field's input type (masked vs. plain) must be declared by the
 * backend that actually knows what the field is, never guessed
 * client-side from its name (a heuristic keyed on "password"/"secret"/
 * "key" only ever grows as new field names appear, and silently
 * mis-renders the day one doesn't match).
 *
 * `type` is intentionally a plain validated string, not a native PHP
 * enum -- mirrors SettingDefinition::$type's own convention for the
 * identical reason (Configuration Platform's own field-type concept).
 */
final class ProviderCredentialFieldDefinition
{
    public const TYPE_TEXT = 'text';

    public const TYPE_PASSWORD = 'password';

    public const TYPE_SECRET = 'secret';

    public const VALID_TYPES = [self::TYPE_TEXT, self::TYPE_PASSWORD, self::TYPE_SECRET];

    public function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {}
}
