<?php

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\ValueObjects\ProviderCredentialFieldDefinition;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Models\ProviderRegistration;
use App\Modules\Administration\Services\ProviderRegistry;

/**
 * Proves ProviderRegistry::sync()'s registration-time guards with real
 * negative cases -- mirrors tests/Feature/Administration/
 * ConfigurationRegistrySyncTest.php's discipline exactly, applied to the
 * Provider Registry's own mandatory fields (ADR-0019 Decision 1,
 * Playbook Phase 2's "a distinct, narrower permission gates them").
 */
interface FakeCapabilityContract
{
    public function doTheThing(): bool;
}

class NoCredentialFieldsProvider implements DeclaresProviderSlots
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.no-credential-fields',
                capabilityContract: 'test.category',
                credentialFields: [],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
        ];
    }
}

class MissingPermissionProvider implements DeclaresProviderSlots
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.missing-permission',
                capabilityContract: 'test.category',
                credentialFields: [new ProviderCredentialFieldDefinition('api_key', 'text')],
                owningModule: 'Test',
                requiredPermissionToEdit: '',
            ),
        ];
    }
}

class ApprovalRequiredWithoutPermissionProvider implements DeclaresProviderSlots
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.approval-no-permission',
                capabilityContract: 'test.category',
                credentialFields: [new ProviderCredentialFieldDefinition('api_key', 'text')],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
                approvalRequired: true,
                approvalPermission: null,
            ),
        ];
    }
}

/**
 * Declares a REAL interface (FakeCapabilityContract) as its capability
 * contract but does not implement it -- the negative case for
 * assertCapabilityContractSatisfied()'s reflective check.
 */
class ContractMismatchProvider implements DeclaresProviderSlots
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.contract-mismatch',
                capabilityContract: FakeCapabilityContract::class,
                credentialFields: [new ProviderCredentialFieldDefinition('api_key', 'text')],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
        ];
    }
}

/**
 * The negative case for assertCredentialFieldTypesValid()'s bare-string
 * rejection -- a bare string in credentialFields is exactly what this
 * codebase's own Phase 2 scaffold looked like before §27.4/§27.5's
 * pre-freeze amendment; proving it's refused, not silently accepted.
 */
class BareStringCredentialFieldsProvider implements DeclaresProviderSlots
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.bare-string-fields',
                capabilityContract: 'test.category',
                credentialFields: ['api_key'],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
        ];
    }
}

class InvalidFieldTypeProvider implements DeclaresProviderSlots
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.invalid-field-type',
                capabilityContract: 'test.category',
                credentialFields: [new ProviderCredentialFieldDefinition('api_key', 'integer')],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
        ];
    }
}

class ValidProvider implements DeclaresProviderSlots, FakeCapabilityContract
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.valid-provider',
                capabilityContract: FakeCapabilityContract::class,
                credentialFields: [
                    new ProviderCredentialFieldDefinition('api_key', 'text'),
                    new ProviderCredentialFieldDefinition('api_secret', 'secret'),
                ],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
        ];
    }

    public function doTheThing(): bool
    {
        return true;
    }
}

it('refuses to register a slot with no declared credential fields', function () {
    config(['administration.registered_provider_slots' => [NoCredentialFieldsProvider::class]]);

    app(ProviderRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'credentialFields must declare at least one field');

it('refuses a bare string in credentialFields -- every field must declare its own type explicitly', function () {
    config(['administration.registered_provider_slots' => [BareStringCredentialFieldsProvider::class]]);

    app(ProviderRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'must be ProviderCredentialFieldDefinition instances, not bare strings');

it('refuses a credential field declaring an unknown type', function () {
    config(['administration.registered_provider_slots' => [InvalidFieldTypeProvider::class]]);

    app(ProviderRegistry::class)->sync();
})->throws(InvalidArgumentException::class, "declares unknown type 'integer'");

it('refuses to register a slot missing requiredPermissionToEdit', function () {
    config(['administration.registered_provider_slots' => [MissingPermissionProvider::class]]);

    app(ProviderRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'requiredPermissionToEdit is mandatory');

it('refuses to register an approval-required slot with no approval permission', function () {
    config(['administration.registered_provider_slots' => [ApprovalRequiredWithoutPermissionProvider::class]]);

    app(ProviderRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'no approvalPermission declared');

it('refuses to register a slot whose provider class does not implement its own declared capability contract', function () {
    config(['administration.registered_provider_slots' => [ContractMismatchProvider::class]]);

    app(ProviderRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'does not implement its own declared capability contract');

it('registers a valid slot and is idempotent across repeated syncs', function () {
    config(['administration.registered_provider_slots' => [ValidProvider::class]]);

    app(ProviderRegistry::class)->sync();
    $result = app(ProviderRegistry::class)->sync();

    expect($result['synced'])->toBe(['test.valid-provider'])
        ->and(ProviderRegistration::where('slot_key', 'test.valid-provider')->count())->toBe(1)
        ->and(ProviderRegistration::where('slot_key', 'test.valid-provider')->first()->provider_class)->toBe(ValidProvider::class);
});
