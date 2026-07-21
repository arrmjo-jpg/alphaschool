<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Backend half of the Admin Platform Foundation's one required backend
 * surface (docs/ADMIN_PLATFORM.md "Layering", ADR-0015 Decision 7):
 * "which workspace definitions can the current user access," computed
 * server-side from Permission Groups, never decided client-side.
 *
 * Phase E-B (docs/ADMIN_DESIGN_SYSTEM.md §26.13): the first real entry,
 * `configuration-platform`, gated on the same `identity.view-otp-settings`
 * permission Configuration Platform's own field-level view check already
 * uses (backend/app/Modules/Identity/Support/IdentityOtpSettings.php) --
 * one real permission, not an invented workspace-only one. `is_super_admin`
 * bypasses here deliberately, mirroring `MeController`'s own coarse
 * nav-gating stance ("Coarse nav-gating data only; real authorization is
 * always each endpoint's own Policy") and the frontend's already-stated
 * assumption (admin/src/platform/auth/use-me.ts's own comment) that this
 * layer bypasses for Super Admin -- unlike `SettingsResolver::assertCanEdit()`,
 * which is pre-existing business logic this phase does not touch and which
 * has no such bypass, by design (write access stays strict even for
 * Super Admin unless a real permission is granted).
 */
class WorkspaceAccessResolver
{
    /**
     * @return array<int, array{key: string, required_permission: string}>
     */
    public function resolve(User $user): array
    {
        $workspaces = [];

        if ($this->hasPermission($user, 'identity.view-otp-settings')) {
            $workspaces[] = ['key' => 'configuration-platform', 'required_permission' => 'identity.view-otp-settings'];
        }

        // Administration Workspace Phase F-B (docs/ADMIN_DESIGN_SYSTEM.md
        // §27.6, resolved by explicit confirmation): a dedicated
        // workspace-visibility permission, deliberately not inferred from
        // the union of the four per-slot edit permissions
        // (identity.manage-oauth-provider, etc.) -- that would be a
        // synthetic rule needing hand-maintenance every time a slot is
        // added or removed. Individual slot metadata itself has no
        // per-slot view permission at all (§27.2/§27.6); this permission
        // gates only whether the workspace nav item exists.
        if ($this->hasPermission($user, 'administration.providers.view')) {
            $workspaces[] = ['key' => 'provider-registry', 'required_permission' => 'administration.providers.view'];
        }

        return $workspaces;
    }

    /**
     * Sprint 3.1's own known gotcha, reproduced here exactly as
     * `SettingsResolver::assertCanEdit()` already does: `hasPermissionTo()`
     * throws `PermissionDoesNotExist` (not a clean `false`) for a genuinely
     * unseeded permission.
     */
    private function hasPermission(User $user, string $permission): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        try {
            return $user->hasPermissionTo($permission, 'sanctum');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
