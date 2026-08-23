<?php

namespace App\Modules\People\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\People\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * A minimal read-only lookup endpoint (UI Sprint 2, docs/ADMIN_DESIGN_SYSTEM.md
 * §30.5) -- HomeroomAssignment's Create form is the first real consumer
 * needing "which employee can I assign," and no Employee list endpoint
 * existed anywhere in the API surface. Deliberately not a full Employee
 * CRUD workspace (out of scope, a future HR/People workspace's own
 * job) -- just enough to answer the question, mirroring §29.5's
 * `BranchController` precedent exactly, including reusing the same
 * already-seeded `academic.manage-catalog` permission rather than
 * inventing a new one (this lookup only ever backs an Academic
 * assignment form; a genuine standalone Employee directory would need
 * its own permission, not this one).
 */
class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_super_admin) {
            try {
                abort_unless($user->hasPermissionTo('academic.manage-catalog', 'sanctum'), 403);
            } catch (PermissionDoesNotExist) {
                abort(403);
            }
        }

        $employees = Employee::query()
            ->where('lifecycle_status', Employee::STATUS_ACTIVE)
            ->with('person')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name_en' => $employee->person->name()->fullNameEn(),
                'name_ar' => $employee->person->name()->fullNameAr(),
            ])
            ->sortBy('name_en')
            ->values();

        return response()->json(['data' => $employees]);
    }
}
