<?php

namespace App\Modules\People\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\People\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * UI Sprint 2's SectionAssignment slice (docs/ADMIN_DESIGN_SYSTEM.md
 * §31.2) -- Enrollment's own anchor lookup for the Assignments
 * workspace's live student search, mirroring EmployeeController's
 * minimal, read-only shape exactly (§31.9). Lives in People (Foundation),
 * consumed by Academic's frontend -- the same direction EmployeeController
 * already established for HomeroomAssignment.
 *
 * Deliberately never references App\Modules\Academic\Models\AcademicYear
 * or GradeLevel anywhere in this file, even for the "which year is open"
 * scoping `search()` applies -- deptrac.yaml's Foundation ruleset
 * forbids People depending on Academic (Domain), the exact reason
 * Enrollment itself carries no academicYear()/gradeLevel() relation
 * (ADR-0026, see Enrollment's own docblock). `academic_year_id` is
 * therefore a REQUIRED client-supplied parameter, not resolved here:
 * the Academic-side frontend already owns "which year is open" (via
 * `academicYearProvider`, the same loader Section's own async-select
 * fields use) and passes the id through; this controller only ever
 * applies it as a plain FK equality filter on `enrollments.academic_year_id`,
 * never a query against `academic_years` itself. The value is never
 * trusted as proof the year is genuinely open -- it only narrows the
 * search result set. `AssignSectionAction::assertAcademicYearIsOpen()`
 * remains the real, final guard, re-checked at the moment of `store()`.
 */
class EnrollmentController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2'],
            'academic_year_id' => ['required', 'integer'],
        ]);

        $term = '%'.$validated['q'].'%';

        $enrollments = Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('people', 'people.id', '=', 'students.person_id')
            ->where('enrollments.academic_year_id', $validated['academic_year_id'])
            ->where('enrollments.status', Enrollment::STATUS_ACTIVE)
            ->where(function ($nameQuery) use ($term) {
                $nameQuery->where('people.first_name_en', 'like', $term)
                    ->orWhere('people.family_name_en', 'like', $term)
                    ->orWhere('people.first_name_ar', 'like', $term)
                    ->orWhere('people.family_name_ar', 'like', $term);
            })
            ->orderBy('people.first_name_en')
            ->limit(20)
            ->select('enrollments.*')
            ->with(['student.person', 'branch'])
            ->get()
            ->map(fn (Enrollment $enrollment) => $this->transform($enrollment));

        return response()->json(['data' => $enrollments]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $enrollment = Enrollment::query()->with(['student.person', 'branch'])->findOrFail($id);

        return response()->json($this->transform($enrollment));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Enrollment $enrollment): array
    {
        return [
            'enrollment_id' => $enrollment->id,
            'student_name_en' => $enrollment->student->person->name()->fullNameEn(),
            'student_name_ar' => $enrollment->student->person->name()->fullNameAr(),
            'student_public_id' => $enrollment->student->public_id,
            'branch_id' => $enrollment->branch_id,
            'branch_name_en' => $enrollment->branch->name_en,
            'branch_name_ar' => $enrollment->branch->name_ar,
            'academic_year_id' => $enrollment->academic_year_id,
            'grade_level_id' => $enrollment->grade_level_id,
            'status' => $enrollment->status,
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
