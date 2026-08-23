<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Core\ValueObjects\ReasonCode;
use App\Http\Controllers\Controller;
use App\Modules\Academic\Actions\AssignHomeroomTeacherAction;
use App\Modules\Academic\Models\HomeroomAssignment;
use App\Modules\Academic\Models\Section;
use App\Modules\People\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The Temporal Assignment Workspace's first real backend adapter (UI
 * Sprint 2, docs/ADMIN_DESIGN_SYSTEM.md §30.6) -- deliberately NOT a
 * subclass of AcademicMasterDataController (§29): there is no generic
 * "status" to PATCH and no update() at all, since HasTemporalAssignment
 * itself forbids editing a record in place. Four actions only: index
 * (a Section's own timeline), store (create, via the existing
 * AssignHomeroomTeacherAction -- reused, not reimplemented), close, and
 * cancel (both call the model's own inherited closeAssignment()/
 * cancelAssignment() directly, matching Sprint B's own precedent that
 * neither needs a dedicated orchestrating Action). No destroy() --
 * matches §28.7's discipline extended to this pattern: a temporal
 * record is retired via cancelAssignment(), never deleted.
 *
 * This is the first HTTP consumer of AssignHomeroomTeacherAction and of
 * HasTemporalAssignment's closeAssignment()/cancelAssignment() methods
 * -- no prior HTTP-layer convention exists for translating their
 * RuntimeException-family failures (ClosedAcademicYearException, the
 * overlap guard, invalid-reason-for-context) or the ReasonCode value
 * object's own InvalidArgumentException (malformed code shape) into a
 * JSON response, so this controller establishes it: caught narrowly
 * around each Action/method call and surfaced as a 422, never a raw 500
 * for an expected business-rule violation. Per §30.4's own instruction
 * ("surfaces as an ordinary inline field error via the existing
 * mapServerErrors"), the response uses Laravel's standard validation
 * shape (`message` + `errors: {field: [message]}`), not a bare
 * `message` -- the frontend's mapServerErrors only renders an inline
 * field error when `errors` is present; a bare `message` would silently
 * fall back to the generic toast and lose the specific reason. store()'s
 * failures (overlap, closed year) carry no field of their own -- Section
 * is fixed by the route, not a form field -- so they attach to
 * `employee_id`, the nearest real field on the Create form; close()/
 * cancel()'s reason-code failures attach to `reason_code` exactly.
 */
class HomeroomAssignmentController extends Controller
{
    public function __construct(private readonly AssignHomeroomTeacherAction $assignHomeroomTeacherAction) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $request->validate(['section_id' => ['required', 'integer', Rule::exists('sections', 'id')]]);

        $assignments = HomeroomAssignment::query()
            ->where('section_id', $request->integer('section_id'))
            ->with(['employee.person', 'reasonCode', 'endedBy.person'])
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (HomeroomAssignment $assignment) => $this->transform($assignment));

        return response()->json(['data' => $assignments]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'effective_from' => ['nullable', 'date'],
        ]);

        try {
            $assignment = $this->assignHomeroomTeacherAction->execute(
                Section::findOrFail($validated['section_id']),
                Employee::findOrFail($validated['employee_id']),
                $validated['effective_from'] ?? null,
            );
        } catch (RuntimeException $exception) {
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

        $assignment = HomeroomAssignment::findOrFail($id);

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
            // Ambiguous origin by this point -- closeAssignment() can
            // still fail on an unregistered (but well-formed) reason
            // code, on DateRange's own half-open-interval invariant (a
            // same-day close: effective_until defaults to today, equal
            // to effective_from for a same-day assignment, producing an
            // empty [today, today) range), or on guardAgainstOverlap().
            // No exception subclass distinguishes these (all three throw
            // RuntimeException or InvalidArgumentException from inside
            // the same call), so the field is chosen from each
            // exception's own known, fixed message template
            // (resolveReasonCodeId()/DateRange/guardAgainstOverlap all
            // format their messages deterministically) rather than
            // guessing -- reason-code failures still attach to
            // `reason_code`; date-range and overlap failures (both
            // genuinely about the record's period) attach to
            // `effective_until`. Either way, still a 422, never a raw
            // 500.
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

        $assignment = HomeroomAssignment::findOrFail($id);

        try {
            $assignment->cancelAssignment(new ReasonCode($validated['reason_code']), $request->user());
        } catch (RuntimeException|InvalidArgumentException $exception) {
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
    private function transform(HomeroomAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'section_id' => $assignment->section_id,
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
