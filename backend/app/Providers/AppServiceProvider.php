<?php

namespace App\Providers;

use App\Modules\Identity\Models\User;
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

        // docs/DOMAIN_BLUEPRINT.md §8: Super Admin is a Gate::before
        // bypass keyed off an account flag, entirely outside the Role
        // system -- NOT a role granted per branch/team. A per-team role
        // grant would need to be remembered every time a new branch is
        // created (a silent access-gap risk); this bypass instead
        // short-circuits every ability check before any policy or role
        // is even consulted, so it automatically covers a branch (or
        // any other resource) that didn't exist when this line was
        // written.
        Gate::before(fn (User $user) => $user->is_super_admin ? true : null);
    }
}
