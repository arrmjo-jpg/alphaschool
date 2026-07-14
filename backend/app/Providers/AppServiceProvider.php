<?php

namespace App\Providers;

use App\Modules\Administration\Support\ApprovalRoutingResolver as AdministrationApprovalRoutingResolver;
use App\Modules\Administration\Support\SingleRoleApprovalRoutingResolver as AdministrationSingleRoleApprovalRoutingResolver;
use App\Modules\Identity\Models\User;
use App\Modules\IdentityMaintenance\Models\MergeRequest;
use App\Modules\IdentityMaintenance\Policies\MergeRequestPolicy;
use App\Modules\IdentityMaintenance\Support\ApprovalRoutingResolver;
use App\Modules\IdentityMaintenance\Support\MergeFieldResolver;
use App\Modules\IdentityMaintenance\Support\SingleRoleApprovalRoutingResolver;
use App\Modules\IdentityMaintenance\Support\WinningPersonAlwaysWinsFieldResolver;
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
        // Sprint 3.2 -- bound here, not resolved by convention, so
        // swapping either default implementation later (a multi-role
        // ApprovalRoutingResolver, a richer MergeFieldResolver) is a
        // one-line change, never a MergeOrchestrationService edit.
        $this->app->bind(ApprovalRoutingResolver::class, SingleRoleApprovalRoutingResolver::class);
        $this->app->bind(MergeFieldResolver::class, WinningPersonAlwaysWinsFieldResolver::class);

        // Administration Platform's own, independently-declared copy of
        // the same pattern (Phase 1) -- not shared with
        // IdentityMaintenance's binding above, per Blueprint B1's
        // promotion-not-prediction rule (two consumers do not yet
        // justify promoting this to Core) and Administration's own
        // deptrac boundary (Administration: [Core] only).
        $this->app->bind(AdministrationApprovalRoutingResolver::class, AdministrationSingleRoleApprovalRoutingResolver::class);
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
        Gate::policy(MergeRequest::class, MergeRequestPolicy::class);

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
