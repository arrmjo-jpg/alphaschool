<?php

namespace App\Modules\Identity\Support;

use App\Core\Contracts\DeclaresSettingsSchema;
use App\Core\ValueObjects\SettingDefinition;

/**
 * The Administration Platform Phase 1 proof consumer
 * (docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md) -- retrofits the two OTP
 * parameters App\Modules\Identity\Services\StepUpAuthenticationService
 * already hardcoded (code length, challenge lifetime) through the
 * Configuration Platform. Deliberately does NOT declare "attempts" or
 * "resend-delay" -- neither exists as real behavior in
 * StepUpAuthenticationService today, and declaring a Configuration key
 * with no real consumer would be exactly the kind of speculative
 * registration Blueprint B1's promotion-not-prediction rule refuses
 * elsewhere. Registered into config('administration.registered_settings_schemas').
 *
 * Both keys carry Data Classification "identity" with
 * approvalRequired = false, which trips ADR-0018 Decision 10's
 * registration-time integrity heuristic (condition c) -- the
 * heuristicAcknowledgment below is what lets ConfigurationRegistry::sync()
 * accept them anyway, a real, load-bearing exercise of that guard, not
 * a synthetic one.
 */
class IdentityOtpSettings implements DeclaresSettingsSchema
{
    private const HEURISTIC_ACKNOWLEDGMENT = 'OTP code length and challenge lifetime are routine technical parameters, not identity-graph-altering operations (contrast Person Merge/Anonymization, Addendum C10) -- approval-gating would add ceremony with no safety benefit. Acknowledged per ADR-0018 Decision 10.';

    public static function settingsSchema(): array
    {
        return [
            new SettingDefinition(
                key: 'identity.otp.code_length',
                type: 'int',
                eligibleAltitudes: ['global'],
                owningModule: 'Identity',
                capability: 'access-governance',
                dataClassification: 'identity',
                requiredPermissionToView: 'identity.view-otp-settings',
                requiredPermissionToEdit: 'identity.configure-otp-settings',
                defaultValue: 6,
                required: true,
                validationRules: ['type' => 'int', 'min' => 4, 'max' => 10],
                heuristicAcknowledgment: self::HEURISTIC_ACKNOWLEDGMENT,
            ),
            new SettingDefinition(
                key: 'identity.otp.lifetime_minutes',
                type: 'int',
                eligibleAltitudes: ['global'],
                owningModule: 'Identity',
                capability: 'access-governance',
                dataClassification: 'identity',
                requiredPermissionToView: 'identity.view-otp-settings',
                requiredPermissionToEdit: 'identity.configure-otp-settings',
                defaultValue: 5,
                required: true,
                validationRules: ['type' => 'int', 'min' => 1, 'max' => 60],
                heuristicAcknowledgment: self::HEURISTIC_ACKNOWLEDGMENT,
            ),
        ];
    }
}
