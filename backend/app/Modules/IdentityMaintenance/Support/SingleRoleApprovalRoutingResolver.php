<?php

namespace App\Modules\IdentityMaintenance\Support;

use App\Modules\Identity\Models\Permission;
use RuntimeException;

/**
 * Today's policy, not an architectural limit (Sprint 3.2): exactly one
 * role must currently hold the named permission. Zero roles or more
 * than one both throw clearly rather than guessing which role governs
 * -- the same "don't silently guess among ambiguous candidates"
 * discipline ADR-0008 already applies to preferred-contact selection.
 *
 * This is the ONLY class in the ApprovalRoutingResolver family that
 * knows Spatie exists -- the interface itself does not.
 */
class SingleRoleApprovalRoutingResolver implements ApprovalRoutingResolver
{
    public function resolveSteps(string $permission, string $guard = 'sanctum'): array
    {
        $permissionModel = Permission::where('name', $permission)->where('guard_name', $guard)->first();

        if ($permissionModel === null) {
            throw new RuntimeException(
                "ApprovalRoutingResolver: permission '{$permission}' (guard '{$guard}') does not exist -- seed it before requesting an approval routed through it."
            );
        }

        $roleNames = $permissionModel->roles()->pluck('name');

        if ($roleNames->isEmpty()) {
            throw new RuntimeException(
                "ApprovalRoutingResolver: no role currently holds '{$permission}' -- assign it to exactly one role before this approval can be routed."
            );
        }

        if ($roleNames->count() > 1) {
            throw new RuntimeException(
                "ApprovalRoutingResolver: '{$permission}' is held by more than one role (".$roleNames->implode(', ').
                ') -- this resolver\'s current policy requires exactly one, since ApprovalEngine\'s step shape cannot express "any of several roles" for a single step. Reduce to one role, or replace this resolver with one that returns a multi-step chain.'
            );
        }

        return [
            ['required_role' => $roleNames->first()],
        ];
    }
}
