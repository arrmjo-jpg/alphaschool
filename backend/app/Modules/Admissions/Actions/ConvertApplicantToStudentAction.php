<?php

namespace App\Modules\Admissions\Actions;

use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\Admissions\Contracts\Billable;
use App\Modules\Admissions\Exceptions\ApplicantAlreadyConvertedException;
use App\Modules\Admissions\Exceptions\ApplicantNotAcceptedException;
use App\Modules\Admissions\Exceptions\PaymentNotConfirmedException;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\People\Events\StudentEnrolled;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * The synchronous conversion action (Sprint 4.3 Technical
 * Specification) -- lives in Admissions (ADR-0026), not People:
 * deptrac.yaml classifies People under Foundation and Admissions under
 * Domain, and Foundation must never depend on Domain. This Action's own
 * dependencies (Applicant, Billable, AcademicCatalogService) are all
 * Domain-side, so the Action belongs where it can import them legally
 * -- Domain -> Foundation (this Action reading/writing Student and
 * Enrollment, both People/Foundation) is the permitted direction.
 * People still owns all Student/Enrollment writes; only the calling
 * code's module location changed from the original Sprint 4.3
 * specification.
 */
class ConvertApplicantToStudentAction
{
    public function __construct(
        private readonly AcademicCatalogService $academicCatalogService,
        private readonly Billable $billable,
    ) {}

    public function execute(Applicant $applicant): Student
    {
        return DB::transaction(function () use ($applicant): Student {
            // lockForUpdate() makes the double-conversion concurrency
            // test meaningful -- two simultaneous callers serialize
            // here, the loser sees the already-converted status.
            $locked = Applicant::query()->whereKey($applicant->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Applicant::STATUS_ACCEPTED) {
                throw $locked->status === Applicant::STATUS_CONVERTED
                    ? new ApplicantAlreadyConvertedException($locked)
                    : new ApplicantNotAcceptedException($locked);
            }

            $this->academicCatalogService->assertAcademicYearIsOpen($locked->academic_year_id);

            if (! $this->billable->isPaid($locked)) {
                throw new PaymentNotConfirmedException($locked);
            }

            $student = Student::where('person_id', $locked->person_id)->first();

            if ($student === null) {
                // Explicit, matching SubmitApplicationAction's own
                // Applicant::create() precedent -- relying on the
                // column's DB-level default would leave this in-memory
                // model's lifecycle_status null until a fresh()/
                // refresh(), since Eloquent never re-queries after
                // create().
                $student = Student::create([
                    'person_id' => $locked->person_id,
                    'lifecycle_status' => Student::STATUS_ACTIVE,
                ]);
            } else {
                $student->reactivate();
            }

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $locked->academic_year_id,
                'branch_id' => $locked->branch_id,
                'grade_level_id' => $locked->applied_for_grade_level_id,
                'status' => Enrollment::STATUS_ACTIVE,
            ]);

            $student->current_enrollment_id = $enrollment->id;
            $student->save();

            $locked->convert();

            // DB::afterCommit(), not ->afterCommit() chained on
            // dispatch() -- StudentEnrolled is a plain event (no
            // ShouldQueue), and Dispatchable::dispatch() returns void/an
            // array of listener results, not anything chainable. This
            // is the correct Laravel mechanism for deferring any
            // callback -- queued or not -- until this transaction
            // actually commits (closes Sprint 4.2's own disclosed debt
            // item on ApplicationSubmitted).
            DB::afterCommit(function () use ($student, $enrollment): void {
                StudentEnrolled::dispatch($student, $enrollment);
            });

            return $student;
        });
    }
}
