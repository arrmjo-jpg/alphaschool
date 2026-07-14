<?php

namespace App\Core\ValueObjects;

/**
 * The fifteen-field metadata model
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 2, amended by Decisions 9-10) a module declares one instance
 * of per Configuration key, returned from
 * App\Core\Contracts\DeclaresSettingsSchema::settingsSchema(). A pure
 * value object -- no persistence, no side effects.
 * App\Modules\Administration\Services\ConfigurationRegistry is the only
 * consumer that turns these into real
 * App\Modules\Administration\Models\ConfigurationDefinition rows.
 *
 * Deliberately excludes three fields proposed and rejected during
 * review (ADR-0018 Decision 2): an audit-required toggle (Configuration
 * audit is unconditional, never optional), an encrypted flag (a field
 * needing encryption is a Provider Credential, not Configuration), and
 * an environment flag (only a key's value may vary by environment,
 * never its existence).
 */
final class SettingDefinition
{
    /**
     * @param  string[]  $eligibleAltitudes  e.g. ["global", "branch"] -- ConfigurationValue::ALTITUDE_* constants.
     * @param  array<int, array{key: string, value: mixed}>  $requires  Simple equality preconditions only -- no expression language (ADR-0018 Decision 2).
     * @param  array<string, mixed>  $validationRules  e.g. ['type' => 'int', 'min' => 1, 'max' => 999999].
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly array $eligibleAltitudes,
        public readonly string $owningModule,
        public readonly string $capability,
        public readonly string $dataClassification,
        public readonly string $requiredPermissionToView,
        public readonly string $requiredPermissionToEdit,
        public readonly ?string $translatableCategory = null,
        public readonly mixed $defaultValue = null,
        public readonly bool $required = false,
        public readonly bool $versioned = false,
        public readonly bool $approvalRequired = false,
        public readonly ?string $approvalPermission = null,
        public readonly bool $restartRequired = false,
        public readonly ?int $cacheTtlSeconds = null,
        public readonly array $requires = [],
        public readonly array $validationRules = [],
        public readonly ?string $migrationStrategy = null,
        public readonly string $deprecationStatus = 'active',
        public readonly ?string $heuristicAcknowledgment = null,
    ) {}
}
