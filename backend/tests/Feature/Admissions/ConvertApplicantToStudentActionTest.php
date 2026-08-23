<?php

use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Admissions\Actions\ConvertApplicantToStudentAction;
use App\Modules\Admissions\Exceptions\ApplicantAlreadyConvertedException;
use App\Modules\Admissions\Exceptions\ApplicantNotAcceptedException;
use App\Modules\Admissions\Exceptions\PaymentNotConfirmedException;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Events\StudentEnrolled;
use App\Modules\People\Events\StudentReactivated;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;

/**
 * Sprint 4.3 Technical Specification §14 -- the full conversion action
 * suite. Lives in Admissions (ADR-0026), matching
 * ConvertApplicantToStudentAction's own module after the Independent
 * Review's Finding 1 relocation. Every accepted+paid Applicant here uses
 * an active Academic Year with a real Branch/GradeLevel, matching the
 * fixtures SubmitApplicationActionTest.php already established for
 * Sprint 4.2.
 */
function acceptedPaidApplicant(array $overrides = []): Applicant
{
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $gradeLevel = GradeLevel::factory()->create();

    return Applicant::factory()->accepted()->feePaid()->create(array_merge([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'applied_for_grade_level_id' => $gradeLevel->id,
    ], $overrides));
}

it('converts a first-time Applicant into a brand-new Student with an active Enrollment', function () {
    Event::fake([StudentEnrolled::class]);

    $applicant = acceptedPaidApplicant();

    $student = app(ConvertApplicantToStudentAction::class)->execute($applicant);

    expect($student->person_id)->toBe($applicant->person_id)
        ->and($student->lifecycle_status)->toBe(Student::STATUS_ACTIVE)
        ->and($student->current_enrollment_id)->not->toBeNull();

    $enrollment = Enrollment::find($student->current_enrollment_id);
    expect($enrollment->student_id)->toBe($student->id)
        ->and($enrollment->academic_year_id)->toBe($applicant->academic_year_id)
        ->and($enrollment->branch_id)->toBe($applicant->branch_id)
        ->and($enrollment->grade_level_id)->toBe($applicant->applied_for_grade_level_id)
        ->and($enrollment->status)->toBe(Enrollment::STATUS_ACTIVE);

    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_CONVERTED)
        ->and($applicant->fresh()->converted_at)->not->toBeNull();

    Event::assertDispatched(StudentEnrolled::class, fn ($event) => $event->student->is($student) && $event->enrollment->is($enrollment));
});

it('reactivates a returning Student from withdrawn back to active on re-conversion', function () {
    Event::fake([StudentEnrolled::class, StudentReactivated::class]);

    $existingStudent = Student::factory()->withdrawn()->create();
    $applicant = acceptedPaidApplicant(['person_id' => $existingStudent->person_id]);

    $student = app(ConvertApplicantToStudentAction::class)->execute($applicant);

    expect($student->id)->toBe($existingStudent->id)
        ->and($student->fresh()->lifecycle_status)->toBe(Student::STATUS_ACTIVE)
        ->and(Student::count())->toBe(1);

    Event::assertDispatched(StudentReactivated::class, fn ($event) => $event->student->is($student));
});

it('reactivates a returning Student from graduated back to active on re-conversion', function () {
    Event::fake([StudentEnrolled::class, StudentReactivated::class]);

    $existingStudent = Student::factory()->graduated()->create();
    $applicant = acceptedPaidApplicant(['person_id' => $existingStudent->person_id]);

    $student = app(ConvertApplicantToStudentAction::class)->execute($applicant);

    expect($student->id)->toBe($existingStudent->id)
        ->and($student->fresh()->lifecycle_status)->toBe(Student::STATUS_ACTIVE);

    Event::assertDispatched(StudentReactivated::class, fn ($event) => $event->student->is($student));
});

it('sets current_enrollment_id correctly on both the new-Student and returning-Student paths', function () {
    $newApplicant = acceptedPaidApplicant();
    $newStudent = app(ConvertApplicantToStudentAction::class)->execute($newApplicant);
    expect($newStudent->fresh()->current_enrollment_id)->toBe($newStudent->fresh()->currentEnrollment->id);

    $existingStudent = Student::factory()->withdrawn()->create();
    $returningApplicant = acceptedPaidApplicant(['person_id' => $existingStudent->person_id]);
    $returnedStudent = app(ConvertApplicantToStudentAction::class)->execute($returningApplicant);
    expect($returnedStudent->fresh()->current_enrollment_id)->toBe($returnedStudent->fresh()->currentEnrollment->id)
        ->and($returnedStudent->currentEnrollment->academic_year_id)->toBe($returningApplicant->academic_year_id);
});

it('refuses to convert an Applicant that is not accepted', function () {
    $applicant = acceptedPaidApplicant(['status' => Applicant::STATUS_TESTED]);

    expect(fn () => app(ConvertApplicantToStudentAction::class)->execute($applicant))
        ->toThrow(ApplicantNotAcceptedException::class);

    expect(Student::where('person_id', $applicant->person_id)->exists())->toBeFalse();
});

it('refuses to convert an Applicant that was already converted', function () {
    $applicant = acceptedPaidApplicant();
    app(ConvertApplicantToStudentAction::class)->execute($applicant);

    expect(fn () => app(ConvertApplicantToStudentAction::class)->execute($applicant->fresh()))
        ->toThrow(ApplicantAlreadyConvertedException::class);

    expect(Student::where('person_id', $applicant->person_id)->count())->toBe(1);
    expect(Enrollment::where('student_id', Student::where('person_id', $applicant->person_id)->first()->id)->count())->toBe(1);
});

it('refuses to convert against a closed Academic Year', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->closed()->create();
    $gradeLevel = GradeLevel::factory()->create();

    $applicant = Applicant::factory()->accepted()->feePaid()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'applied_for_grade_level_id' => $gradeLevel->id,
    ]);

    expect(fn () => app(ConvertApplicantToStudentAction::class)->execute($applicant))
        ->toThrow(ClosedAcademicYearException::class);

    expect(Student::where('person_id', $applicant->person_id)->exists())->toBeFalse();
    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_ACCEPTED);
});

it('refuses to convert an Applicant whose registration fee is not confirmed paid', function () {
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $gradeLevel = GradeLevel::factory()->create();

    $applicant = Applicant::factory()->accepted()->feeUnpaid()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'applied_for_grade_level_id' => $gradeLevel->id,
    ]);

    expect(fn () => app(ConvertApplicantToStudentAction::class)->execute($applicant))
        ->toThrow(PaymentNotConfirmedException::class);

    expect(Student::where('person_id', $applicant->person_id)->exists())->toBeFalse();
    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_ACCEPTED);
});

/**
 * Atomicity regression test, matching the discipline Sprint 4.2's own
 * Finding 1 fix established -- forces a failure AFTER Student::save()
 * (here, the reactivate() branch's write) already ran inside the same
 * DB::transaction(), then proves the whole transaction rolled back.
 *
 * Every column Enrollment::create() writes other than student_id
 * (academic_year_id, branch_id, grade_level_id) is ALSO FK-constrained
 * on applicants itself, so an invalid reference there fails
 * Applicant::factory()->create() before the Action ever runs -- it
 * cannot isolate a failure to the Enrollment insert specifically. The
 * one constraint genuinely unique to Enrollment is
 * unique(student_id, academic_year_id), which structurally requires an
 * existing Student with a pre-existing Enrollment for that exact
 * Academic Year -- the returning-guardian path, deliberately
 * constructed here (a duplicate Enrollment row for the same student one
 * academic year, not reachable through the normal single-conversion-
 * per-Applicant flow, but still a real schema-level invariant worth
 * proving rolls back cleanly).
 */
it('rolls back the reactivated Student\'s write when the Enrollment insert fails on the same transaction', function () {
    $existingStudent = Student::factory()->withdrawn()->create();
    $academicYear = AcademicYear::factory()->active()->create();

    // A pre-existing Enrollment already occupies this exact
    // (student_id, academic_year_id) pair -- Enrollment::create() inside
    // the Action will violate the unique constraint.
    Enrollment::factory()->create([
        'student_id' => $existingStudent->id,
        'academic_year_id' => $academicYear->id,
    ]);

    $applicant = acceptedPaidApplicant([
        'person_id' => $existingStudent->person_id,
        'academic_year_id' => $academicYear->id,
    ]);

    expect(fn () => app(ConvertApplicantToStudentAction::class)->execute($applicant))
        ->toThrow(QueryException::class);

    expect($existingStudent->fresh()->lifecycle_status)->toBe(Student::STATUS_WITHDRAWN);
    expect(Student::where('person_id', $applicant->person_id)->count())->toBe(1);
    expect(Enrollment::where('student_id', $existingStudent->id)->count())->toBe(1);
    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_ACCEPTED);
});
