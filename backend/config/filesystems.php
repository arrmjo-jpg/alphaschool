<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        |----------------------------------------------------------------
        | AlphaSchool Media Tiers (docs/DOMAIN_BLUEPRINT.md §12)
        |----------------------------------------------------------------
        |
        | Exactly three logical tiers, never one per category. Application
        | code must only ever reference these three disk names
        | (Storage::disk('public'|'private'|'temporary')) -- never a
        | concrete driver -- so swapping local storage for Cloudflare R2
        | in production is a config change, not a code change, the same
        | "abstract now, pick the backend later" principle already applied
        | to search (Scout) and the Number Generator.
        |
        | Each disk's driver is env-controlled: 'local' for dev (the
        | default below), 's3' for R2 in production, since R2 is
        | S3-API-compatible and needs no custom Laravel driver -- only
        | endpoint/credential config. The local-only keys (root, url,
        | visibility) and the s3-only keys (key, secret, endpoint, bucket)
        | coexist harmlessly in one array; Laravel only reads the keys
        | relevant to whichever driver is actually active.
        |
        */

        'public' => [
            'driver' => env('MEDIA_PUBLIC_DRIVER', 'local'),
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_REGION', 'auto'),
            'bucket' => env('R2_PUBLIC_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

        // Never publicly reachable. CDN must never sit in front of this
        // tier -- CDN edge caching and short-lived signed/authenticated
        // access conflict, per the Media Architecture decision. Served
        // exclusively through the authenticated streaming route, never a
        // direct URL.
        'private' => [
            'driver' => env('MEDIA_PRIVATE_DRIVER', 'local'),
            'root' => storage_path('app/private-media'),
            'visibility' => 'private',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_REGION', 'auto'),
            'bucket' => env('R2_PRIVATE_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

        // Ephemeral. Never a durable business-media home -- exports,
        // in-progress generated files, staging uploads. Purged by a
        // scheduled command (App\Modules\Media\Console\Commands\
        // PurgeTemporaryMedia), never relied upon to persist.
        'temporary' => [
            'driver' => env('MEDIA_TEMPORARY_DRIVER', 'local'),
            'root' => storage_path('app/temporary-media'),
            'visibility' => 'private',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_REGION', 'auto'),
            'bucket' => env('R2_TEMPORARY_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
