<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Core\ValueObjects\ReasonCode;
use App\Http\Controllers\Controller;
use App\Modules\Academic\Actions\AssignTeacherToSubjectOfferingAction;
use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\People\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The Assignments workspace's third Timeline/Action-shaped controller
 * (UI Sprint 2, TeacherAssignment slice, docs/ADMIN_DESIGN_SYSTEM.md
 * §32.6/§32.8) -- mirrors HomeroomAssignmentController's shape exactly,
 * not SectionAssignmentController's: the anchor here (SubjectOffering)
 * is fixed by the route, and Employee is the real form field, the same
 * structural relationship Homeroom has (Section fixed by the route,
 * Employee the form field) -- so store()'s failures attach to
 * `employee_id`, matching Homeroom's own convention exactly.
 *
 * close()'s field-attachment classification (reason-related exception
 * text -> `reason_code`; everything else, genuinely about the record's
 * own period -> `effective_until`) is identical to
 * HomeroomAssignmentController's/SectionAssignmentController's own
 * established convention, reused verbatim a third time.
 */
class TeacherAssignmentController extends Controller
{
    public function __construct(private readonly AssignTeacherToSubjectOfferingAction $assignTeacherToSubjectOfferingAction) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $request->validate(['subject_offering_id' => ['required', 'integer', Rule::exists('subject_offerings', 'id')]]);

        $assignments = TeacherAssignment::query()
            ->where('subject_offering_id', $request->integer('subject_offering_id'))
            ->with(['employee.person', 'reasonCode', 'endedBy.person'])
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (TeacherAssignment $assignment) => $this->transform($assignment));

        return response()->json(['data' => $assignments]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'subject_offering_id' => ['required', 'integer', Rule::exists('subject_offerings', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'effective_from' => ['nullable', 'date'],
        ]);

        try {
            $assignment = $this->assignTeacherToSubjectOfferingAction->execute(
                SubjectOffering::findOrFail($validated['subject_offering_id']),
                Employee::findOrFail($validated['employee_id']),
                $validated['effective_from'] ?? null,
            );
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['employee_id' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json($this->transform($assignment->load(['employee.person', 'reasonCode', 'endedBy.person'])), 201);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'reason_code' => ['required', 'string'],
            'effective_until' => ['nullable', 'date'],
        ]);

        $assignment = TeacherAssignment::findOrFail($id);

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
            $field = str_contains($exception->getMessage(), 'registered, active reason code') ? 'reason_code' : 'effective_until';

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [$field => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json($this->transform($assignment->fresh()->load(['employee.person', 'reasonCode', 'endedBy.person'])));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate(['reason_code' => ['required', 'string']]);

        $assignment = TeacherAssignment::findOrFail($id);

        try {
            $assignment->cancelAssignment(new ReasonCode($validated['reason_code']), $request->user());
        } catch (RuntimeException|InvalidArgumentException $exception) {
            // cancelAssignment() can only ever fail on the reason code
            // itself -- guardAgainstOverlap() skips cancelled records
            // entirely (its own early return), no ambiguity here the
            // way there is for close().
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['reason_code' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json($this->transform($assignment->fresh()->load(['employee.person', 'reasonCode', 'endedBy.person'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(TeacherAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'subject_offering_id' => $assignment->subject_offering_id,
            'employee_id' => $assignment->employee_id,
            'employee_name_en' => $assignment->employee->person->name()->fullNameEn(),
            'employee_name_ar' => $assignment->employee->person->name()->fullNameAr(),
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
