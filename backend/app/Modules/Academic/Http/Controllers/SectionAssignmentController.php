<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Core\ValueObjects\ReasonCode;
use App\Http\Controllers\Controller;
use App\Modules\Academic\Actions\AssignSectionAction;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SectionAssignment;
use App\Modules\People\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The Assignments workspace's second Timeline/Action-shaped controller
 * (UI Sprint 2, SectionAssignment slice, docs/ADMIN_DESIGN_SYSTEM.md
 * §31.6/§31.9) -- mirrors HomeroomAssignmentController's shape exactly:
 * index/store/close/cancel, no update/destroy, deliberately not
 * extending AcademicMasterDataController. The anchor here is Enrollment
 * (fixed by the route), so store()'s failures attach to `section_id` --
 * the real, present form field -- the mirror image of Homeroom's own
 * `employee_id` attachment (there, Section was fixed by the route and
 * Employee was the form field).
 *
 * close()'s field-attachment classification (reason-related text ->
 * `reason_code`; everything else, genuinely about the record's own
 * period -> `effective_until`) is identical to
 * HomeroomAssignmentController's own §30.9-established convention,
 * reused verbatim, not reinvented.
 */
class SectionAssignmentController extends Controller
{
    public function __construct(private readonly AssignSectionAction $assignSectionAction) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $request->validate(['enrollment_id' => ['required', 'integer', Rule::exists('enrollments', 'id')]]);

        $assignments = SectionAssignment::query()
            ->where('enrollment_id', $request->integer('enrollment_id'))
            ->with(['section.gradeLevel', 'reasonCode', 'endedBy.person'])
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (SectionAssignment $assignment) => $this->transform($assignment));

        return response()->json(['data' => $assignments]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'enrollment_id' => ['required', 'integer', Rule::exists('enrollments', 'id')],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'effective_from' => ['nullable', 'date'],
        ]);

        try {
            $assignment = $this->assignSectionAction->execute(
                Enrollment::findOrFail($validated['enrollment_id']),
                Section::findOrFail($validated['section_id']),
                $validated['effective_from'] ?? null,
            );
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['section_id' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json($this->transform($assignment->load(['section.gradeLevel', 'reasonCode', 'endedBy.person'])), 201);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'reason_code' => ['required', 'string'],
            'effective_until' => ['nullable', 'date'],
        ]);

        $assignment = SectionAssignment::findOrFail($id);

        try {
            $reason = new ReasonCode($validated['reason_code']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['reason_code' => [$exception->getMessage()]],
            ], 422);
        }

        try {
            $assignment->closeAssignment($reason, $validated['effective_until'] ?? null, $request->user());
        } catch (RuntimeException|InvalidArgumentException $exception) {
            // Same ambiguity HomeroomAssignmentController already
            // resolved (§30.9/§31.6): closeAssignment() can still fail on
            // an unregistered (but well-formed) reason code, on
            // DateRange's own half-open-interval invariant (a same-day
            // close), or on guardAgainstOverlap() -- none distinguished
            // by exception subclass, so classified by each exception's
            // own known, fixed message content instead.
            $field = str_contains($exception->getMessage(), 'registered, active reason code') ? 'reason_code' : 'effective_until';

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [$field => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json($this->transform($assignment->fresh()->load(['section.gradeLevel', 'reasonCode', 'endedBy.person'])));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate(['reason_code' => ['required', 'string']]);

        $assignment = SectionAssignment::findOrFail($id);

        try {
            $assignment->cancelAssignment(new ReasonCode($validated['reason_code']), $request->user());
        } catch (RuntimeException|InvalidArgumentException $exception) {
            // cancelAssignment() can only ever fail on the reason code
            // itself -- guardAgainstOverlap() skips cancelled records
            // entirely (its own early return), so no ambiguity exists
            // here the way it does for close().
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['reason_code' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json($this->transform($assignment->fresh()->load(['section.gradeLevel', 'reasonCode', 'endedBy.person'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(SectionAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'enrollment_id' => $assignment->enrollment_id,
            'section_id' => $assignment->section_id,
            'section_name' => $assignment->section->name,
            'grade_level_name_en' => $assignment->section->gradeLevel->name_en,
            'grade_level_name_ar' => $assignment->section->gradeLevel->name_ar,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_until' => $assignment->effective_until?->toDateString(),
            'status' => $assignment->status,
            'reason_code' => $assignment->reasonCode?->code,
            'ended_by_id' => $assignment->ended_by_id,
            'ended_by_name_en' => $assignment->endedBy?->person?->name()->fullNameEn(),
            'ended_by_name_ar' => $assignment->endedBy?->person?->name()->fullNameAr(),
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
