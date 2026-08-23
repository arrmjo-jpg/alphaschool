<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\SubjectOffering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * UI Sprint 2's TeacherAssignment slice (docs/ADMIN_DESIGN_SYSTEM.md
 * §32.3) -- SubjectOffering's own minimal, read-only lookup, powering
 * the Landing page's third cascading picker (derived from real
 * SubjectOfferings, never the raw Subject catalog) and the Timeline/
 * Create/Close/Cancel pages' own anchor-context loading.
 *
 * Lives in Academic (Domain), not People (Foundation) -- unlike
 * EnrollmentController (§31.2), SubjectOffering and every model in its
 * own relation chain (Subject/Section/Term/AcademicYear) all live in
 * this same module, so there is no Foundation->Domain boundary to
 * avoid. The response resolves full display names directly for exactly
 * that reason -- a real simplification over EnrollmentController's own
 * raw-FK-id-only response, confirmed safe by §32.8's own re-verification
 * against backend/deptrac.yaml, not assumed from precedent.
 *
 * Deliberately not a full CRUD surface -- SubjectOffering creation
 * remains backend-only via the existing CreateSubjectOfferingAction
 * (§32.3, §32.11); no admin UI for creating offerings is built here.
 */
class SubjectOfferingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'section_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        $offerings = SubjectOffering::query()
            ->when($validated['section_id'] ?? null, fn ($query, $value) => $query->where('section_id', $value))
            ->when($validated['term_id'] ?? null, fn ($query, $value) => $query->where('term_id', $value))
            ->when($validated['subject_id'] ?? null, fn ($query, $value) => $query->where('subject_id', $value))
            ->with(['subject', 'section', 'term', 'academicYear'])
            ->get()
            ->map(fn (SubjectOffering $offering) => $this->transform($offering));

        return response()->json(['data' => $offerings]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $offering = SubjectOffering::query()->with(['subject', 'section', 'term', 'academicYear'])->findOrFail($id);

        return response()->json($this->transform($offering));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(SubjectOffering $offering): array
    {
        return [
            'id' => $offering->id,
            'subject_id' => $offering->subject_id,
            'subject_name_en' => $offering->subject->name_en,
            'subject_name_ar' => $offering->subject->name_ar,
            'section_id' => $offering->section_id,
            'section_name' => $offering->section->name,
            'term_id' => $offering->term_id,
            'term_name_en' => $offering->term->name_en,
            'term_name_ar' => $offering->term->name_ar,
            'academic_year_id' => $offering->academic_year_id,
            'academic_year_name_en' => $offering->academicYear->name_en,
            'academic_year_name_ar' => $offering->academicYear->name_ar,
        ];
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();

        if ($user->is_super_admin) {
            return;
        }

        try {
            abort_unless($user->hasPermissionTo('academic.manage-catalog', 'sanctum'), 403);
        } catch (PermissionDoesNotExist) {
            abort(403);
        }
    }
}
