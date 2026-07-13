<?php

namespace App\Modules\IdentityMaintenance\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\IdentityMaintenance\Models\MergeRequest;

/**
 * Gates REACHING each action -- eligibility for a specific approval
 * STEP is ApprovalEngine's own job (delegated inside
 * MergeOrchestrationService::approve()/approveRollback()), never
 * re-implemented here. Uses hasPermissionTo($permission, 'sanctum')
 * explicitly, not can() -- Sprint 3.1 found this app's default auth
 * guard ('web') silently breaks can()'s permission resolution, since
 * every permission here is seeded under 'sanctum'.
 */
class MergeRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('identity.request-merge', 'sanctum');
    }

    public function requestApproval(User $user, MergeRequest $mergeRequest): bool
    {
        return $user->hasPermissionTo('identity.request-merge', 'sanctum');
    }

    public function approve(User $user, MergeRequest $mergeRequest): bool
    {
        return $user->hasPermissionTo('identity.approve-merge', 'sanctum');
    }

    public function reject(User $user, MergeRequest $mergeRequest): bool
    {
        return $user->hasPermissionTo('identity.approve-merge', 'sanctum');
    }

    /**
     * Sprint 3.2's final review: rollback requires the same approval
     * discipline as the merge itself -- gated by the same permission,
     * not a separate one invented with no stated need.
     */
    public function rollback(User $user, MergeRequest $mergeRequest): bool
    {
        return $user->hasPermissionTo('identity.approve-merge', 'sanctum');
    }
}
