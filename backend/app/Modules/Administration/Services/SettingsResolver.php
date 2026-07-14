<?php

namespace App\Modules\Administration\Services;

use App\Core\Services\ApprovalEngine;
use App\Core\ValueObjects\AltitudeCheck;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\ResolvedSetting;
use App\Modules\Administration\Exceptions\ConfigurationWriteConflictException;
use App\Modules\Administration\Models\ConfigurationDefinition;
use App\Modules\Administration\Models\ConfigurationValue;
use App\Modules\Administration\Support\ApprovalRoutingResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The Configuration Platform's read/write mechanism
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decisions 3-5, amended by Decisions 8-10). Pull-based, never pushes
 * values outward -- a module calls resolve() when it needs a value, the
 * same idiom already proven by the Assignment pattern's asOf(date)
 * (Blueprint §6).
 */
class SettingsResolver
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
        private readonly ApprovalRoutingResolver $approvalRoutingResolver,
    ) {}

    public function resolve(string $key, ConfigurationScopeContext $scope): ResolvedSetting
    {
        $definition = $this->definitionOrFail($key);
        $eligible = $definition->eligible_altitudes;
        $trace = [];

        if ($scope->branchId !== null && in_array('branch', $eligible, true)) {
            $row = $this->currentRow($key, 'branch', $scope->branchId);
            $trace[] = new AltitudeCheck('branch', $row !== null);

            if ($row !== null) {
                return new ResolvedSetting($key, $row->value, 'branch', $trace, $row->version);
            }
        }

        if (in_array('global', $eligible, true)) {
            $row = $this->currentRow($key, 'global', null);
            $trace[] = new AltitudeCheck('global', $row !== null);

            if ($row !== null) {
                return new ResolvedSetting($key, $row->value, 'global', $trace, $row->version);
            }
        }

        // No row at any eligible altitude -- the declared default,
        // never treated as if it were a real altitude row (version 0
        // signals "nothing exists yet" to a subsequent write()'s
        // optimistic-locking check).
        return new ResolvedSetting($key, $definition->default_value, null, $trace, 0);
    }

    /**
     * @throws ConfigurationWriteConflictException if $expectedVersion does not match the current row (Decision 8).
     */
    public function write(string $key, mixed $value, ConfigurationScopeContext $scope, int $expectedVersion, Model $actor): ConfigurationValue
    {
        $definition = $this->definitionOrFail($key);
        $altitude = $scope->altitude();

        if (! in_array($altitude, $definition->eligible_altitudes, true)) {
            throw new InvalidArgumentException("SettingsResolver: '{$key}' is not eligible for {$altitude}-altitude overrides.");
        }

        $this->assertCanEdit($actor, $definition);
        $this->assertValid($definition, $value);

        return DB::transaction(function () use ($definition, $key, $value, $scope, $altitude, $expectedVersion, $actor) {
            $current = ConfigurationValue::where('configuration_key', $key)
                ->where('altitude', $altitude)
                ->where('branch_id', $scope->branchId)
                ->where('status', '!=', ConfigurationValue::STATUS_SUPERSEDED)
                ->lockForUpdate()
                ->first();

            $currentVersion = $current?->version ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw new ConfigurationWriteConflictException($key, $expectedVersion, $currentVersion);
            }

            return $definition->approval_required
                ? $this->writeWithApproval($definition, $key, $value, $altitude, $scope, $current, $actor)
                : $this->writeDirectly($definition, $key, $value, $altitude, $scope, $current, $actor);
        });
    }

    private function writeDirectly(ConfigurationDefinition $definition, string $key, mixed $value, string $altitude, ConfigurationScopeContext $scope, ?ConfigurationValue $current, Model $actor): ConfigurationValue
    {
        if ($definition->versioned) {
            // Blueprint §7: never overwrite history for a calculation-
            // feeding key -- close the current row, append a new one,
            // both stay queryable forever.
            $current?->update(['effective_until' => now()]);

            return ConfigurationValue::create([
                'configuration_key' => $key,
                'altitude' => $altitude,
                'branch_id' => $scope->branchId,
                'value' => $value,
                'version' => ($current?->version ?? 0) + 1,
                'status' => ConfigurationValue::STATUS_ACTIVE,
                'effective_from' => now(),
                'updated_by_id' => $actor->getKey(),
            ]);
        }

        if ($current === null) {
            return ConfigurationValue::create([
                'configuration_key' => $key,
                'altitude' => $altitude,
                'branch_id' => $scope->branchId,
                'value' => $value,
                'version' => 1,
                'status' => ConfigurationValue::STATUS_ACTIVE,
                'updated_by_id' => $actor->getKey(),
            ]);
        }

        $current->update([
            'value' => $value,
            'version' => $current->version + 1,
            'updated_by_id' => $actor->getKey(),
        ]);

        return $current->fresh();
    }

    private function writeWithApproval(ConfigurationDefinition $definition, string $key, mixed $value, string $altitude, ConfigurationScopeContext $scope, ?ConfigurationValue $current, Model $actor): ConfigurationValue
    {
        $pending = ConfigurationValue::create([
            'configuration_key' => $key,
            'altitude' => $altitude,
            'branch_id' => $scope->branchId,
            'value' => $value,
            'version' => ($current?->version ?? 0) + 1,
            'status' => ConfigurationValue::STATUS_PENDING_APPROVAL,
            'updated_by_id' => $actor->getKey(),
        ]);

        $steps = $this->approvalRoutingResolver->resolveSteps($definition->approval_permission);
        $approvalRequest = $this->approvalEngine->request($pending, $steps, $actor);

        $pending->update(['approval_request_id' => $approvalRequest->id]);

        return $pending->fresh();
    }

    /**
     * Called once ApprovalEngine records a decision on a pending
     * Configuration write -- never invoked directly by a caller
     * skipping the approval step.
     */
    public function activateApprovedWrite(ConfigurationValue $pending): ConfigurationValue
    {
        if ($pending->status !== ConfigurationValue::STATUS_PENDING_APPROVAL) {
            throw new RuntimeException("SettingsResolver: ConfigurationValue #{$pending->id} is not pending approval.");
        }

        return DB::transaction(function () use ($pending) {
            ConfigurationValue::where('configuration_key', $pending->configuration_key)
                ->where('altitude', $pending->altitude)
                ->where('branch_id', $pending->branch_id)
                ->where('status', ConfigurationValue::STATUS_ACTIVE)
                ->update(['status' => ConfigurationValue::STATUS_SUPERSEDED, 'effective_until' => now()]);

            $pending->update(['status' => ConfigurationValue::STATUS_ACTIVE]);

            return $pending->fresh();
        });
    }

    private function currentRow(string $key, string $altitude, ?int $branchId): ?ConfigurationValue
    {
        return ConfigurationValue::where('configuration_key', $key)
            ->where('altitude', $altitude)
            ->where('branch_id', $branchId)
            ->where('status', ConfigurationValue::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->orderByDesc('effective_from')
            ->first();
    }

    private function definitionOrFail(string $key): ConfigurationDefinition
    {
        $definition = ConfigurationDefinition::where('key', $key)->first();

        if ($definition === null) {
            throw new InvalidArgumentException("SettingsResolver: unknown Configuration key '{$key}' -- not registered. Run `php artisan administration:sync-settings` after declaring it.");
        }

        return $definition;
    }

    private function assertCanEdit(Model $actor, ConfigurationDefinition $definition): void
    {
        if (! method_exists($actor, 'hasPermissionTo')) {
            throw new RuntimeException("SettingsResolver: actor lacks the '{$definition->required_permission_to_edit}' permission required to edit '{$definition->key}'.");
        }

        // Sprint 3.1's own known gotcha, reproduced here: hasPermissionTo()
        // throws PermissionDoesNotExist (not a clean false) for a
        // genuinely unseeded permission -- caught explicitly so a
        // missing-permission actor gets the same domain-level
        // RuntimeException regardless of whether the permission has ever
        // been seeded, never a raw Spatie exception surfacing as a 500.
        try {
            $allowed = $actor->hasPermissionTo($definition->required_permission_to_edit, 'sanctum');
        } catch (PermissionDoesNotExist) {
            $allowed = false;
        }

        if (! $allowed) {
            throw new RuntimeException("SettingsResolver: actor lacks the '{$definition->required_permission_to_edit}' permission required to edit '{$definition->key}'.");
        }
    }

    private function assertValid(ConfigurationDefinition $definition, mixed $value): void
    {
        $rules = $definition->validation_rules ?? [];

        if (isset($rules['type']) && ! $this->matchesType($value, $rules['type'])) {
            throw new InvalidArgumentException("SettingsResolver: '{$definition->key}' expects type '{$rules['type']}'.");
        }

        if (isset($rules['enum']) && ! in_array($value, $rules['enum'], true)) {
            throw new InvalidArgumentException("SettingsResolver: '{$definition->key}' must be one of [".implode(', ', $rules['enum']).'].');
        }

        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            throw new InvalidArgumentException("SettingsResolver: '{$definition->key}' must be >= {$rules['min']}.");
        }

        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            throw new InvalidArgumentException("SettingsResolver: '{$definition->key}' must be <= {$rules['max']}.");
        }

        if (isset($rules['regex']) && is_string($value) && ! preg_match($rules['regex'], $value)) {
            throw new InvalidArgumentException("SettingsResolver: '{$definition->key}' does not match the required pattern.");
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'string' => is_string($value),
            'array' => is_array($value),
            default => true,
        };
    }
}
