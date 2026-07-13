<?php

namespace App\Modules\IdentityMaintenance\Support;

/**
 * Translates a governance permission name into the step definitions
 * App\Core\Services\ApprovalEngine already understands (Sprint 3.2) --
 * ApprovalEngine itself is never modified to understand permissions, and
 * stays exactly as generic as it was in Sprint 1.2.
 *
 * Provider-agnostic by design: this interface references only plain
 * strings, never a Spatie\Permission\* class, anywhere in its own
 * signature or docblock. Only a concrete implementation (e.g.
 * SingleRoleApprovalRoutingResolver) knows Spatie exists. A future
 * implementation backed by a different permission provider is a new
 * class implementing this same interface -- MergeOrchestrationService
 * and ApprovalEngine both stay unaware of the change.
 *
 * The return shape is deliberately an ORDERED LIST of steps, not a
 * single step -- ApprovalEngine::request() already accepts a multi-step
 * array today. A future sequential multi-approver chain (role A then
 * role B) needs zero change to ApprovalEngine or to this interface,
 * only a resolver implementation that returns more than one entry.
 * "Exactly one role must hold this permission" (today's policy, see
 * SingleRoleApprovalRoutingResolver) is enforced by that concrete class,
 * not by this contract.
 */
interface ApprovalRoutingResolver
{
    /**
     * @return array<int, array{required_role: string}>
     */
    public function resolveSteps(string $permission, string $guard = 'sanctum'): array;
}
