<?php

use App\Modules\Identity\Support\IdentityOtpSettings;

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

];
