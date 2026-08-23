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

        // UI Sprint 1-B (docs/ADMIN_DESIGN_SYSTEM.md §28.17) -- reuses
        // the one permission AcademicYearPolicy/PermissionSeeder already
        // seeded for catalog management (Sprint 4.1), rather than
        // inventing a separate workspace-visibility-only permission the
        // way Provider Registry needed to (§27.6's own resolved gap):
        // Academic's simpler Reference Master Data has no view/edit
        // split to preserve, so one permission gating both is correct
        // here, not a shortcut.
        if ($this->hasPermission($user, 'academic.manage-catalog')) {
            $workspaces[] = ['key' => 'academic', 'required_permission' => 'academic.manage-catalog'];
        }

        // UI Sprint 2 (docs/ADMIN_DESIGN_SYSTEM.md §30.2) -- reuses the
        // same permission HomeroomAssignmentController itself checks
        // server-side, not a new workspace-only one, matching Academic's
        // own precedent immediately above (a temporal Assignment Engine
        // is still catalog-management work, not a separate capability).
        if ($this->hasPermission($user, 'academic.manage-catalog')) {
            $workspaces[] = ['key' => 'assignments', 'required_permission' => 'academic.manage-catalog'];
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
