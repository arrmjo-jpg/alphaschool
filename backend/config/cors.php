<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Published for the admin/ SPA (Admin Platform Foundation, ADR-0015)
    | and the frontend/ guardian/student portal. Auth is bearer-token
    | (Sanctum personal access tokens, see AuthController) never
    | cookie/session-based, so `supports_credentials` stays false -- no
    | Sanctum stateful-domain concept is introduced by this file.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
