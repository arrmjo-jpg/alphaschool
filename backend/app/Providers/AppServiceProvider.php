<?php

namespace App\Providers;

use App\Modules\Media\Models\Media;
use App\Modules\Media\Policies\MediaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Explicit registration -- Media lives under App\Modules\Media\
        // Models, not App\Models, so Laravel's policy auto-discovery
        // (which only looks at App\Models by convention) will not find
        // App\Modules\Media\Policies\MediaPolicy on its own.
        Gate::policy(Media::class, MediaPolicy::class);
    }
}
