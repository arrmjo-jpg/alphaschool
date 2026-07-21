<?php

namespace App\Modules\Administration\Services;

use App\Core\Services\ApprovalEngine;
use App\Core\ValueObjects\AltitudeCheck;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\ResolvedProviderCredential;
use App\Modules\Administration\Exceptions\ProviderCredentialWriteConflictException;
use App\Modules\Administration\Models\ProviderCredential;
use App\Modules\Administration\Models\ProviderRegistration;
use App\Modules\Administration\Support\ApprovalRoutingResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The Credential Vault's read/write mechanism
 * (docs/adr/0019-integration-platform-architecture.md Decision 5,
 * reusing ADR-0018 Decision 8's write-contract algorithm per ADR-0022
 * §1's "using the Configuration Platform's own mechanisms directly").
 * Structurally parallel to SettingsResolver -- same trace-returning
 * altitude chain, same optimistic-locking write contract, same
 * permission-then-validate-then-lock sequencing -- but its own class,
 * its own table, and always-versioned writes (Decision 5: "never
 * overwritten"). Never touches configuration_definitions/
 * configuration_values, and SettingsResolver/ConfigurationRegistry are
 * never touched by this class, satisfying "no change to Configuration
 * APIs."
 */
class ProviderCredentialVault
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
        private readonly ApprovalRoutingResolver $approvalRoutingResolver,
    ) {}

    public function resolve(string $slotKey, ConfigurationScopeContext $scope): ResolvedProviderCredential
    {
        $this->registrationOrFail($slotKey);
        $trace = [];

        if ($scope->branchId !== null) {
            $row = $this->currentRow($slotKey, 'branch', $scope->branchId);
            $trace[] = new AltitudeCheck('branch', $row !== null);

            if ($row !== null) {
                return new ResolvedProviderCredential($slotKey, $row->credentials, 'branch', $trace, $row->version);
            }
        }

        $row = $this->currentRow($slotKey, 'global', null);
        $trace[] = new AltitudeCheck('global', $row !== null);

        if ($row !== null) {
            return new ResolvedProviderCredential($slotKey, $row->credentials, 'global', $trace, $row->version);
        }

        // No credential registered at any altitude yet -- unlike
        // Configuration, there is no declared default for a secret;
        // callers must treat a null resolution as "not configured."
        return new ResolvedProviderCredential($slotKey, null, null, $trace, 0);
    }

    /**
     * @param  array<string, mixed>  $credentials
     *
     * @throws ProviderCredentialWriteConflictException if $expectedVersion does not match the current row (Decision 8's algorithm).
     */
    public function write(string $slotKey, array $credentials, ConfigurationScopeContext $scope, int $expectedVersion, Model $actor): ProviderCredential
    {
        $registration = $this->registrationOrFail($slotKey);
        $altitude = $scope->altitude();

        $this->assertCanEdit($actor, $registration);
        $this->assertCredentialShape($registration, $credentials);

        return DB::transaction(function () use ($registration, $slotKey, $credentials, $scope, $altitude, $expectedVersion, $actor) {
            $current = ProviderCredential::where('slot_key', $slotKey)
                ->where('altitude', $altitude)
                ->where('branch_id', $scope->branchId)
                ->where('status', '!=', ProviderCredential::STATUS_SUPERSEDED)
                ->lockForUpdate()
                ->first();

            $currentVersion = $current?->version ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw new ProviderCredentialWriteConflictException($slotKey, $expectedVersion, $currentVersion);
            }

            return $registration->approval_required
                ? $this->writeWithApproval($registration, $slotKey, $credentials, $altitude, $scope, $current, $actor)
                : $this->writeDirectly($slotKey, $credentials, $altitude, $scope, $current, $actor);
        });
    }

    private function writeDirectly(string $slotKey, array $credentials, string $altitude, ConfigurationScopeContext $scope, ?ProviderCredential $current, Model $actor): ProviderCredential
    {
        // Always versioned (Decision 5) -- close the current row, append
        // a new one, both stay queryable forever. There is no
        // update-in-place path here, unlike SettingsResolver's
        // versioned/non-versioned branch.
        $current?->update(['effective_until' => now()]);

        return ProviderCredential::create([
            'slot_key' => $slotKey,
            'altitude' => $altitude,
            'branch_id' => $scope->branchId,
            'credentials' => $credentials,
            'version' => ($current?->version ?? 0) + 1,
            'status' => ProviderCredential::STATUS_ACTIVE,
            'effective_from' => now(),
            'updated_by_id' => $actor->getKey(),
        ]);
    }

    private function writeWithApproval(ProviderRegistration $registration, string $slotKey, array $credentials, string $altitude, ConfigurationScopeContext $scope, ?ProviderCredential $current, Model $actor): ProviderCredential
    {
        $pending = ProviderCredential::create([
            'slot_key' => $slotKey,
            'altitude' => $altitude,
            'branch_id' => $scope->branchId,
            'credentials' => $credentials,
            'version' => ($current?->version ?? 0) + 1,
            'status' => ProviderCredential::STATUS_PENDING_APPROVAL,
            'updated_by_id' => $actor->getKey(),
        ]);

        $steps = $this->approvalRoutingResolver->resolveSteps($registration->approval_permission);
        $approvalRequest = $this->approvalEngine->request($pending, $steps, $actor);

        $pending->update(['approval_request_id' => $approvalRequest->id]);

        return $pending->fresh();
    }

    /**
     * Called once ApprovalEngine records a decision on a pending
     * credential write -- never invoked directly by a caller skipping
     * the approval step.
     */
    public function activateApprovedWrite(ProviderCredential $pending): ProviderCredential
    {
        if ($pending->status !== ProviderCredential::STATUS_PENDING_APPROVAL) {
            throw new RuntimeException("ProviderCredentialVault: ProviderCredential #{$pending->id} is not pending approval.");
        }

        return DB::transaction(function () use ($pending) {
            ProviderCredential::where('slot_key', $pending->slot_key)
                ->where('altitude', $pending->altitude)
                ->where('branch_id', $pending->branch_id)
                ->where('status', ProviderCredential::STATUS_ACTIVE)
                ->update(['status' => ProviderCredential::STATUS_SUPERSEDED, 'effective_until' => now()]);

            $pending->update(['status' => ProviderCredential::STATUS_ACTIVE]);

            return $pending->fresh();
        });
    }

    private function currentRow(string $slotKey, string $altitude, ?int $branchId): ?ProviderCredential
    {
        return ProviderCredential::where('slot_key', $slotKey)
            ->where('altitude', $altitude)
            ->where('branch_id', $branchId)
            ->where('status', ProviderCredential::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->orderByDesc('effective_from')
            ->first();
    }

    private function registrationOrFail(string $slotKey): ProviderRegistration
    {
        $registration = ProviderRegistration::where('slot_key', $slotKey)->first();

        if ($registration === null) {
            throw new InvalidArgumentException("ProviderCredentialVault: unknown Provider slot '{$slotKey}' -- not registered. Run `php artisan administration:sync-providers` after declaring it.");
        }

        return $registration;
    }

    private function assertCanEdit(Model $actor, ProviderRegistration $registration): void
    {
        if (! method_exists($actor, 'hasPermissionTo')) {
            throw new RuntimeException("ProviderCredentialVault: actor lacks the '{$registration->required_permission_to_edit}' permission required to edit '{$registration->slot_key}'.");
        }

        // Identical Sprint 3.1/Phase 1 gotcha guarded the same way
        // SettingsResolver::assertCanEdit() already does.
        try {
            $allowed = $actor->hasPermissionTo($registration->required_permission_to_edit, 'sanctum');
        } catch (PermissionDoesNotExist) {
            $allowed = false;
        }

        if (! $allowed) {
            throw new RuntimeException("ProviderCredentialVault: actor lacks the '{$registration->required_permission_to_edit}' permission required to edit '{$registration->slot_key}'.");
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function assertCredentialShape(ProviderRegistration $registration, array $credentials): void
    {
        // credential_fields is stored as [{name, type}, ...] (§27.4/§27.5
        // pre-freeze amendment) -- only the names matter for shape
        // validation, the Vault never cares about a field's render type.
        $declared = array_column($registration->credential_fields, 'name');
        $missing = array_diff($declared, array_keys($credentials));
        $unexpected = array_diff(array_keys($credentials), $declared);

        if ($missing !== []) {
            throw new InvalidArgumentException("Provider slot '{$registration->slot_key}': missing required credential field(s): ".implode(', ', $missing).'.');
        }

        if ($unexpected !== []) {
            throw new InvalidArgumentException("Provider slot '{$registration->slot_key}': unexpected credential field(s) not declared by the Registry: ".implode(', ', $unexpected).'.');
        }
    }
}
