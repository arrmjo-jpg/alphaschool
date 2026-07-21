<?php

namespace App\Modules\Administration\Services;

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\ValueObjects\ProviderCredentialFieldDefinition;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Models\ProviderRegistration;
use InvalidArgumentException;

/**
 * The Provider Registry's sync mechanism
 * (docs/adr/0019-integration-platform-architecture.md Decision 1) --
 * reads every registered DeclaresProviderSlots implementer's
 * declarations and writes them into provider_registrations. Mirrors
 * App\Modules\Administration\Services\ConfigurationRegistry's own
 * discipline exactly: the only place these rows are ever written, run
 * at deploy time (`php artisan administration:sync-providers`), never
 * as a side effect of a request.
 *
 * Self-registration convention: the class implementing
 * DeclaresProviderSlots for a given slot IS that slot's provider_class
 * -- one class both declares what it needs and implements the
 * capability contract, so adding a new vendor is exactly "write one new
 * class, add one config line" (ADR-0019 Decision 1), never a change to
 * this service or to any existing Provider.
 */
class ProviderRegistry
{
    /**
     * @return array{synced: string[], flagged: string[]}
     */
    public function sync(): array
    {
        $synced = [];
        $flagged = [];

        foreach (config('administration.registered_provider_slots', []) as $class) {
            if (! is_subclass_of($class, DeclaresProviderSlots::class) && ! in_array(DeclaresProviderSlots::class, class_implements($class) ?: [], true)) {
                throw new InvalidArgumentException("Administration::sync: {$class} is registered in config('administration.registered_provider_slots') but does not implement DeclaresProviderSlots.");
            }

            foreach ($class::providerSlots() as $definition) {
                $this->assertCredentialFieldsDeclared($definition);
                $this->assertCredentialFieldTypesValid($definition);
                $this->assertMandatoryPermission($definition);
                $this->assertApprovalPermissionWhenRequired($definition);
                $this->assertCapabilityContractSatisfied($class, $definition);

                $this->upsert($class, $definition);
                $synced[] = $definition->slotKey;
            }
        }

        return ['synced' => $synced, 'flagged' => $flagged];
    }

    private function assertCredentialFieldsDeclared(ProviderSlotDefinition $definition): void
    {
        if ($definition->credentialFields === []) {
            throw new InvalidArgumentException(
                "Provider slot '{$definition->slotKey}': credentialFields must declare at least one field -- a slot with no credential shape is not a vendor relationship the Vault has anything to store.",
            );
        }
    }

    /**
     * §27.4/§27.5 pre-freeze amendment: each field's `type` must be one
     * of the frontend's known render types -- refused at registration
     * time, never silently defaulted, matching this class's own
     * standing discipline for every other guard here.
     */
    private function assertCredentialFieldTypesValid(ProviderSlotDefinition $definition): void
    {
        foreach ($definition->credentialFields as $field) {
            if (! $field instanceof ProviderCredentialFieldDefinition) {
                throw new InvalidArgumentException(
                    "Provider slot '{$definition->slotKey}': credentialFields must be ProviderCredentialFieldDefinition instances, not bare strings -- each field declares its own type explicitly.",
                );
            }

            if (! in_array($field->type, ProviderCredentialFieldDefinition::VALID_TYPES, true)) {
                $validTypes = implode(', ', ProviderCredentialFieldDefinition::VALID_TYPES);
                throw new InvalidArgumentException(
                    "Provider slot '{$definition->slotKey}': credential field '{$field->name}' declares unknown type '{$field->type}' -- must be one of: {$validTypes}.",
                );
            }
        }
    }

    private function assertMandatoryPermission(ProviderSlotDefinition $definition): void
    {
        if (trim($definition->requiredPermissionToEdit) === '') {
            throw new InvalidArgumentException(
                "Provider slot '{$definition->slotKey}': requiredPermissionToEdit is mandatory -- registration refused, never defaulted to open access (mirrors ADR-0018 Decision 9's identical Configuration guard, per Playbook Phase 2's 'a distinct, narrower permission gates them' requirement).",
            );
        }
    }

    private function assertApprovalPermissionWhenRequired(ProviderSlotDefinition $definition): void
    {
        if ($definition->approvalRequired && trim((string) $definition->approvalPermission) === '') {
            throw new InvalidArgumentException(
                "Provider slot '{$definition->slotKey}': approvalRequired = true but no approvalPermission declared -- ApprovalRoutingResolver has no routing target.",
            );
        }
    }

    /**
     * Reflective, runtime-string check only (class_implements() on a
     * string) -- never a static `use` import of the owning module's
     * capability-contract interface, so this stays inside
     * Administration's deptrac ruleset ([Core] only) even though the
     * interface it is checking against lives in Notifications or
     * Identity.
     */
    private function assertCapabilityContractSatisfied(string $providerClass, ProviderSlotDefinition $definition): void
    {
        if (! interface_exists($definition->capabilityContract)) {
            return;
        }

        $implements = class_implements($providerClass) ?: [];

        if (! in_array($definition->capabilityContract, $implements, true)) {
            throw new InvalidArgumentException(
                "Provider slot '{$definition->slotKey}': {$providerClass} does not implement its own declared capability contract {$definition->capabilityContract}.",
            );
        }
    }

    private function upsert(string $providerClass, ProviderSlotDefinition $definition): void
    {
        ProviderRegistration::updateOrCreate(
            ['slot_key' => $definition->slotKey],
            [
                'capability_contract' => $definition->capabilityContract,
                'provider_class' => $providerClass,
                'credential_fields' => array_map(
                    fn (ProviderCredentialFieldDefinition $field) => ['name' => $field->name, 'type' => $field->type],
                    $definition->credentialFields,
                ),
                'owning_module' => $definition->owningModule,
                'required_permission_to_edit' => $definition->requiredPermissionToEdit,
                'approval_required' => $definition->approvalRequired,
                'approval_permission' => $definition->approvalPermission,
            ],
        );
    }
}
