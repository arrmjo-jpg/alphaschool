<?php

namespace App\Modules\Administration\Support;

use RuntimeException;
use Spatie\Permission\Models\Permission;

/**
 * Today's policy, not an architectural limit: exactly one role must
 * currently hold the named permission -- the same policy
 * IdentityMaintenance's own resolver already enforces (Sprint 3.2).
 *
 * Queries Spatie's own base Permission model directly, NOT
 * App\Modules\Identity\Models\Permission -- Administration Platform's
 * boundary (deptrac's Administration: [Core] ruleset,
 * tests/Architecture/AdministrationPlatformBoundaryTest.php) forbids
 * depending on any App\Modules\* namespace outside itself. This is safe:
 * Identity's Permission subclass only adds Translatable display fields
 * on top of the same underlying `permissions` table Spatie's base class
 * already queries correctly -- name, guard_name, and the roles()
 * relationship this class actually needs are all on the base class.
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
                ') -- this resolver\'s current policy requires exactly one, since ApprovalEngine\'s step shape cannot express "any of several roles" for a single step.'
            );
        }

        return [
            ['required_role' => $roleNames->first()],
        ];
    }
}
