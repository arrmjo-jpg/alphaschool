<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * A minimal read-only lookup endpoint (UI Sprint 1-B, docs/ADMIN_DESIGN_SYSTEM.md
 * §28.17) -- Section is the first Academic entity with a real Branch
 * FK (branch_id), and no Branch list endpoint existed anywhere in the
 * API surface to populate that field's dropdown. Deliberately not a
 * full Branch CRUD workspace (out of scope here, a future Identity
 * workspace's own job) -- just enough to answer "which branches can I
 * pick from," gated by the already-seeded `branches.view` permission
 * (PermissionSeeder), no new permission invented.
 */
class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_super_admin) {
            try {
                abort_unless($user->hasPermissionTo('branches.view', 'sanctum'), 403);
            } catch (PermissionDoesNotExist) {
                abort(403);
            }
        }

        $branches = Branch::query()->where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar']);

        return response()->json(['data' => $branches]);
    }
}
