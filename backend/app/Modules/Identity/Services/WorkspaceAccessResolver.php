<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;

/**
 * Backend half of the Admin Platform Foundation's one required backend
 * surface (docs/ADMIN_PLATFORM.md "Layering", ADR-0015 Decision 7):
 * "which workspace definitions can the current user access," computed
 * server-side from Permission Groups, never decided client-side.
 *
 * Deliberately returns an empty list today -- the frontend workspace
 * registry (admin/src/workspaces/registry.ts) has zero registered
 * workspaces by design (Admin Platform Foundation ships zero business
 * content, ADR-0015 Decision 2), so there is nothing yet to map a
 * Permission Group to. When the first real workspace ships, this is
 * where its permission-to-workspace-key mapping is added -- additive,
 * not a redesign of this class's shape.
 */
class WorkspaceAccessResolver
{
    /**
     * @return array<int, array{key: string, required_permission: string}>
     */
    public function resolve(User $user): array
    {
        return [];
    }
}
