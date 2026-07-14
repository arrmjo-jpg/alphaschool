<?php

use App\Modules\Administration\Console\Commands\SyncConfigurationSchemas;
use App\Modules\Media\Console\Commands\PurgeTemporaryMedia;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // Module-owned commands live under app/Modules/*/Console/Commands,
    // not app/Console/Commands, so Laravel's default auto-discovery path
    // does not find them -- registered explicitly here instead.
    ->withCommands([
        PurgeTemporaryMedia::class,
        SyncConfigurationSchemas::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
