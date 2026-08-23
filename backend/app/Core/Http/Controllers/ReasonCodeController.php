<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\ReasonCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A minimal read-only lookup endpoint (UI Sprint 2, docs/ADMIN_DESIGN_SYSTEM.md
 * §30.5) -- every closeAssignment()/cancelAssignment() call across every
 * HasTemporalAssignment consumer needs a real reason code, and no
 * endpoint existed to list the seeded catalog for a given context.
 * Built once, generically, in Core (ReasonCode's own owning module) --
 * the `context` query param is the only thing that varies across
 * HomeroomAssignment/SectionAssignment/TeacherAssignment, so this
 * endpoint does not need rebuilding per entity when those later slices
 * are built. Deliberately no write endpoint -- reason codes are
 * seeder-managed (docs/DOMAIN_BLUEPRINT.md §8), never admin-UI-creatable.
 *
 * Deliberately gated by `auth:sanctum` alone (at the route level), no
 * additional permission check -- unlike EmployeeController/
 * BranchController, which are correctly scoped to `academic.manage-
 * catalog` because their data backs an Academic-specific form. This
 * endpoint is genuinely cross-module by design (`context` will
 * eventually span HR's `employment`, Admissions' rejection reasons,
 * etc.) -- gating it to one module's permission would be wrong for
 * every consumer that isn't Academic. Reason code labels themselves
 * carry no sensitivity requiring finer gating.
 */
class ReasonCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['context' => ['required', 'string']]);

        // A real bug found live during UI Sprint 2: ->get(['code',
        // 'label']) alone serializes `label` (Spatie HasTranslations)
        // through its locale-resolved accessor -- a single string in
        // whatever the app's own backend locale happens to be,
        // regardless of which language the admin UI's own user has
        // selected. Every other bilingual field in this codebase wires
        // both languages onto the response explicitly (name_en/name_ar);
        // reason codes are made consistent with that convention here
        // rather than shipping the one bilingual field that silently
        // ignores the frontend's own language toggle.
        $reasonCodes = ReasonCode::query()
            ->forContext($request->string('context')->toString())
            ->orderBy('code')
            ->get(['code', 'label'])
            ->map(fn (ReasonCode $reasonCode) => [
                'code' => $reasonCode->code,
                'label_en' => $reasonCode->getTranslation('label', 'en'),
                'label_ar' => $reasonCode->getTranslation('label', 'ar'),
            ]);

        return response()->json(['data' => $reasonCodes]);
    }
}
