<?php

use App\Core\Contracts\DeclaresSettingsSchema;
use App\Core\ValueObjects\SettingDefinition;
use App\Modules\Administration\Models\ConfigurationDefinition;
use App\Modules\Administration\Services\ConfigurationRegistry;

/**
 * Proves ADR-0018 Decisions 9-10's registration-time guards with real
 * negative cases -- each fixture below is a genuine violation the
 * critical review found could otherwise ship silently.
 */
class MissingPermissionFieldsSettings implements DeclaresSettingsSchema
{
    public static function settingsSchema(): array
    {
        return [
            new SettingDefinition(
                key: 'test.missing-permissions',
                type: 'string',
                eligibleAltitudes: ['global'],
                owningModule: 'Test',
                capability: 'policy-configuration-governance',
                dataClassification: 'operational',
                requiredPermissionToView: '',
                requiredPermissionToEdit: '',
            ),
        ];
    }
}

class ApprovalRequiredWithoutPermissionSettings implements DeclaresSettingsSchema
{
    public static function settingsSchema(): array
    {
        return [
            new SettingDefinition(
                key: 'test.approval-no-permission',
                type: 'string',
                eligibleAltitudes: ['global'],
                owningModule: 'Test',
                capability: 'policy-configuration-governance',
                dataClassification: 'operational',
                requiredPermissionToView: 'test.view',
                requiredPermissionToEdit: 'test.edit',
                approvalRequired: true,
                approvalPermission: null,
            ),
        ];
    }
}

class UnacknowledgedSensitiveClassificationSettings implements DeclaresSettingsSchema
{
    public static function settingsSchema(): array
    {
        return [
            new SettingDefinition(
                key: 'test.unacknowledged-financial',
                type: 'string',
                eligibleAltitudes: ['global'],
                owningModule: 'Test',
                capability: 'policy-configuration-governance',
                dataClassification: 'financial',
                requiredPermissionToView: 'test.view',
                requiredPermissionToEdit: 'test.edit',
                approvalRequired: false,
                heuristicAcknowledgment: null,
            ),
        ];
    }
}

class UnacknowledgedComplexValidationSettings implements DeclaresSettingsSchema
{
    public static function settingsSchema(): array
    {
        return [
            new SettingDefinition(
                key: 'test.unacknowledged-complex-validation',
                type: 'string',
                eligibleAltitudes: ['global'],
                owningModule: 'Test',
                capability: 'policy-configuration-governance',
                dataClassification: 'operational',
                requiredPermissionToView: 'test.view',
                requiredPermissionToEdit: 'test.edit',
                validationRules: ['type' => 'string', 'custom_business_rule' => 'grade_average_above_60'],
                heuristicAcknowledgment: null,
            ),
        ];
    }
}

class AcknowledgedFinancialSettings implements DeclaresSettingsSchema
{
    public static function settingsSchema(): array
    {
        return [
            new SettingDefinition(
                key: 'test.acknowledged-financial',
                type: 'string',
                eligibleAltitudes: ['global'],
                owningModule: 'Test',
                capability: 'policy-configuration-governance',
                dataClassification: 'financial',
                requiredPermissionToView: 'test.view',
                requiredPermissionToEdit: 'test.edit',
                approvalRequired: false,
                heuristicAcknowledgment: 'Deliberately not approval-gated -- informational only, no financial impact. Test fixture.',
            ),
        ];
    }
}

it('refuses to register a key missing required-permission-to-view or required-permission-to-edit', function () {
    config(['administration.registered_settings_schemas' => [MissingPermissionFieldsSettings::class]]);

    app(ConfigurationRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'requiredPermissionToView and requiredPermissionToEdit are both mandatory');

it('refuses to register an approval-required key with no approval permission', function () {
    config(['administration.registered_settings_schemas' => [ApprovalRequiredWithoutPermissionSettings::class]]);

    app(ConfigurationRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'no approvalPermission declared');

it('refuses to register a Financial-classified key with approval_required = false and no heuristic acknowledgment', function () {
    config(['administration.registered_settings_schemas' => [UnacknowledgedSensitiveClassificationSettings::class]]);

    app(ConfigurationRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'triggers the registration-time integrity heuristic');

it('refuses to register a key with validation rules beyond type/regex/enum/min/max and no heuristic acknowledgment', function () {
    config(['administration.registered_settings_schemas' => [UnacknowledgedComplexValidationSettings::class]]);

    app(ConfigurationRegistry::class)->sync();
})->throws(InvalidArgumentException::class, 'validation rules exceed');

it('accepts a flagged key once a heuristic acknowledgment is supplied', function () {
    config(['administration.registered_settings_schemas' => [AcknowledgedFinancialSettings::class]]);

    $result = app(ConfigurationRegistry::class)->sync();

    expect($result['synced'])->toBe(['test.acknowledged-financial'])
        ->and($result['flagged'])->toHaveCount(1)
        ->and(ConfigurationDefinition::where('key', 'test.acknowledged-financial')->exists())->toBeTrue();
});

it('is idempotent -- syncing the same schema twice updates the same row rather than duplicating it', function () {
    config(['administration.registered_settings_schemas' => [AcknowledgedFinancialSettings::class]]);

    app(ConfigurationRegistry::class)->sync();
    app(ConfigurationRegistry::class)->sync();

    expect(ConfigurationDefinition::where('key', 'test.acknowledged-financial')->count())->toBe(1);
});
