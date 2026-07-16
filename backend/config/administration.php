<?php

use App\Modules\Identity\Providers\GoogleOAuthProvider;
use App\Modules\Identity\Support\IdentityOtpSettings;
use App\Modules\Media\Providers\R2StorageProvider;
use App\Modules\Notifications\Providers\FirebasePushProvider;
use App\Modules\Notifications\Providers\SmtpEmailProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Registered Settings Schemas
    |--------------------------------------------------------------------------
    |
    | Every App\Core\Contracts\DeclaresSettingsSchema implementer, listed
    | explicitly (docs/adr/0018-configuration-platform-resolution-and-
    | metadata.md: "a deploy-time manifest, code-reviewed, never
    | runtime-mutable") -- the same explicit-registration convention
    | already used for MergeOrchestrationService::REGISTERED_CLASSES
    | (Sprint 3.2), not magic class-scanning. Adding a new module's
    | settings means adding one line here and running
    | `php artisan administration:sync-settings`.
    |
    */

    'registered_settings_schemas' => [
        IdentityOtpSettings::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Provider Slots
    |--------------------------------------------------------------------------
    |
    | Every App\Core\Contracts\DeclaresProviderSlots implementer, listed
    | explicitly for the identical reason as registered_settings_schemas
    | above (docs/adr/0019-integration-platform-architecture.md Decision
    | 1: "adding a new vendor... requires zero changes to the Registry
    | itself"). Adding a new Provider means adding one line here and
    | running `php artisan administration:sync-providers`.
    |
    */

    'registered_provider_slots' => [
        R2StorageProvider::class,
        SmtpEmailProvider::class,
        GoogleOAuthProvider::class,
        FirebasePushProvider::class,
    ],

];
