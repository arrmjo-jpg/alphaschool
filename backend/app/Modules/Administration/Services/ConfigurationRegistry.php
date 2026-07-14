<?php

namespace App\Modules\Administration\Services;

use App\Core\Contracts\DeclaresSettingsSchema;
use App\Core\ValueObjects\SettingDefinition;
use App\Modules\Administration\Models\ConfigurationDefinition;
use InvalidArgumentException;

/**
 * The Configuration Registry's sync mechanism
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 2, amended by Decisions 9-10) -- reads every registered
 * DeclaresSettingsSchema implementer's declarations and writes them
 * into configuration_definitions. This is the only place those rows are
 * ever written; run at deploy time
 * (`php artisan administration:sync-settings`), never as a side effect
 * of a request.
 *
 * Enforces, at sync time rather than trusting the declaring module's
 * own judgment:
 *   - Decision 9: requiredPermissionToView/Edit must both be non-empty
 *     -- a manifest omitting either is refused outright, never silently
 *     defaulted to open access.
 *   - Decision 10: approvalRequired = true must carry a non-empty
 *     approvalPermission (the routing target ApprovalRoutingResolver
 *     needs -- ADR-0018 itself does not specify how approval routing is
 *     computed beyond "routes through the existing Approval Engine";
 *     this is the concrete, minimal mechanism this phase supplies).
 *   - Decision 10's registration-time integrity heuristic: validation
 *     rules exceeding type/regex/enum/min/max, or a Financial/Identity
 *     /Audit Data Classification combined with approvalRequired =
 *     false, must carry a non-empty heuristicAcknowledgment or sync
 *     refuses the key outright -- this is what makes "requires an
 *     explicit reviewer-acknowledgment comment to merge" a checkable
 *     fact, not a hope. Condition (a) of Decision 10 (dependency
 *     fan-out) is deliberately not checked here -- it needs the
 *     Dependency Graph, which does not exist before Phase 5.
 */
class ConfigurationRegistry
{
    private const SENSITIVE_CLASSIFICATIONS = ['identity', 'financial', 'audit'];

    private const ALLOWED_VALIDATION_RULE_KEYS = ['type', 'regex', 'enum', 'min', 'max'];

    /**
     * @return array{synced: string[], flagged: string[]}
     */
    public function sync(): array
    {
        $synced = [];
        $flagged = [];

        foreach (config('administration.registered_settings_schemas', []) as $class) {
            if (! is_subclass_of($class, DeclaresSettingsSchema::class) && ! in_array(DeclaresSettingsSchema::class, class_implements($class) ?: [], true)) {
                throw new InvalidArgumentException("Administration::sync: {$class} is registered in config('administration.registered_settings_schemas') but does not implement DeclaresSettingsSchema.");
            }

            foreach ($class::settingsSchema() as $definition) {
                $this->assertMandatoryPermissions($definition);
                $this->assertApprovalPermissionWhenRequired($definition);

                $heuristicReason = $this->integrityHeuristicReason($definition);
                if ($heuristicReason !== null) {
                    $this->assertAcknowledged($definition, $heuristicReason);
                    $flagged[] = "{$definition->key}: {$heuristicReason}";
                }

                $this->upsert($definition);
                $synced[] = $definition->key;
            }
        }

        return ['synced' => $synced, 'flagged' => $flagged];
    }

    private function assertMandatoryPermissions(SettingDefinition $definition): void
    {
        if (trim($definition->requiredPermissionToView) === '' || trim($definition->requiredPermissionToEdit) === '') {
            throw new InvalidArgumentException(
                "Configuration key '{$definition->key}': requiredPermissionToView and requiredPermissionToEdit are both mandatory (ADR-0018 Decision 9) -- registration refused, never defaulted to open access.",
            );
        }
    }

    private function assertApprovalPermissionWhenRequired(SettingDefinition $definition): void
    {
        if ($definition->approvalRequired && trim((string) $definition->approvalPermission) === '') {
            throw new InvalidArgumentException(
                "Configuration key '{$definition->key}': approvalRequired = true but no approvalPermission declared -- ApprovalRoutingResolver has no routing target.",
            );
        }
    }

    private function integrityHeuristicReason(SettingDefinition $definition): ?string
    {
        $extraKeys = array_diff(array_keys($definition->validationRules), self::ALLOWED_VALIDATION_RULE_KEYS);
        if ($extraKeys !== []) {
            return 'validation rules exceed type/regex/enum/min/max complexity ('.implode(', ', $extraKeys).')';
        }

        if (in_array(strtolower($definition->dataClassification), self::SENSITIVE_CLASSIFICATIONS, true) && ! $definition->approvalRequired) {
            return "Data Classification '{$definition->dataClassification}' combined with approvalRequired = false";
        }

        return null;
    }

    private function assertAcknowledged(SettingDefinition $definition, string $reason): void
    {
        if (trim((string) $definition->heuristicAcknowledgment) === '') {
            throw new InvalidArgumentException(
                "Configuration key '{$definition->key}' triggers the registration-time integrity heuristic ({$reason}) and has no heuristicAcknowledgment (ADR-0018 Decision 10) -- registration refused until a reviewer's justification is supplied.",
            );
        }
    }

    private function upsert(SettingDefinition $definition): void
    {
        ConfigurationDefinition::updateOrCreate(
            ['key' => $definition->key],
            [
                'type' => $definition->type,
                'translatable_category' => $definition->translatableCategory,
                'default_value' => $definition->defaultValue,
                'required' => $definition->required,
                'eligible_altitudes' => $definition->eligibleAltitudes,
                'versioned' => $definition->versioned,
                'owning_module' => $definition->owningModule,
                'capability' => $definition->capability,
                'data_classification' => $definition->dataClassification,
                'approval_required' => $definition->approvalRequired,
                'approval_permission' => $definition->approvalPermission,
                'required_permission_to_view' => $definition->requiredPermissionToView,
                'required_permission_to_edit' => $definition->requiredPermissionToEdit,
                'restart_required' => $definition->restartRequired,
                'cache_ttl_seconds' => $definition->cacheTtlSeconds,
                'requires' => $definition->requires,
                'validation_rules' => $definition->validationRules,
                'migration_strategy' => $definition->migrationStrategy,
                'deprecation_status' => $definition->deprecationStatus,
                'heuristic_acknowledgment' => $definition->heuristicAcknowledgment,
            ],
        );
    }
}
