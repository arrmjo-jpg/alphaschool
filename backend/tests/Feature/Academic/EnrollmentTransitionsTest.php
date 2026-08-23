<?php

use App\Modules\Academic\Actions\GraduateEnrollmentAction;
use App\Modules\Academic\Actions\PromoteEnrollmentAction;
use App\Modules\Academic\Actions\RepeatEnrollmentAction;
use App\Modules\Academic\Actions\WithdrawEnrollmentAction;
use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Exceptions\NoNextGradeLevelException;
use App\Modules\Academic\Exceptions\NotAtFinalGradeLevelException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\BranchGradeLevel;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Events\EnrollmentGraduated;
use App\Modules\People\Events\EnrollmentPromoted;
use App\Modules\People\Events\EnrollmentRepeated;
use App\Modules\People\Events\EnrollmentWithdrawn;
use App\Modules\People\Events\StudentGraduated;
use App\Modules\People\Events\StudentWithdrawn;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Sprint 4.4 Architecture Pass -- Promotion/Repetition/Withdrawal/
 * Graduation. Branch Transfer is deliberately out of scope (deferred,
 * see docs/IMPLEMENTATION_PLAYBOOK.md's Sprint 4.4 entry).
 */
function activeEnrollmentAt(int $sequenceOrder): Enrollment
{
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $gradeLevel = GradeLevel::factory()->create(['sequence_order' => $sequenceOrder]);

    $enrollment = Enrollment::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
        'status' => Enrollment::STATUS_ACTIVE,
    ]);

    // Mirrors what ConvertApplicantToStudentAction (Sprint 4.3) always
    // does in practice -- current_enrollment_id points at this
    // Enrollment before any transition test runs.
    Student::find($enrollment->student_id)->update(['current_enrollment_id' => $enrollment->id]);

    return $enrollment;
}

/**
 * Independent Review Finding 1 -- nextGradeLevelForBranch()/
 * isFinalGradeLevelForBranch() are branch-scoped, so a GradeLevel must
 * have a real BranchGradeLevel row for a given branch before promotion/
 * graduation logic will recognize it as available there.
 */
function offerGradeAtBranch(int $branchId, GradeLevel $gradeLevel): void
{
    BranchGradeLevel::factory()->create(['branch_id' => $branchId, 'grade_level_id' => $gradeLevel->id]);
}

// --- Promotion ---------------------------------------------------------

it('promotes an active Enrollment into a new one at the next grade level', function () {
    Event::fake([EnrollmentPromoted::class]);

    $current = activeEnrollmentAt(500);
    offerGradeAtBranch($current->branch_id, GradeLevel::factory()->create(['sequence_order' => 501]));
    $nextYear = AcademicYear::factory()->active()->create();

    $next = app(PromoteEnrollmentAction::class)->execute($current, $nextYear->id);

    expect($next->id)->not->toBe($current->id)
        ->and($next->status)->toBe(Enrollment::STATUS_ACTIVE)
        ->and($next->student_id)->toBe($current->student_id)
        ->and($next->academic_year_id)->toBe($nextYear->id)
        ->and($next->branch_id)->toBe($current->branch_id)
        ->and($next->previous_enrollment_id)->toBe($current->id);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_PROMOTED)
        ->and($current->fresh()->next_enrollment_id)->toBe($next->id);

    expect(Student::find($current->student_id)->current_enrollment_id)->toBe($next->id);

    Event::assertDispatched(EnrollmentPromoted::class, fn ($event) => $event->previous->is($current) && $event->next->is($next));
});

it('refuses to promote when there is no next grade level in sequence', function () {
    $current = activeEnrollmentAt(999999);
    $nextYear = AcademicYear::factory()->active()->create();

    expect(fn () => app(PromoteEnrollmentAction::class)->execute($current, $nextYear->id))
        ->toThrow(NoNextGradeLevelException::class);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_ACTIVE);
});

/**
 * Independent Review Finding 1 regression test -- a higher-sequence
 * GradeLevel exists globally, but this Enrollment's own branch has no
 * BranchGradeLevel row offering it. Before the fix,
 * AcademicCatalogService::nextGradeLevel() ignored BranchGradeLevel
 * entirely and would have let this promotion succeed, creating an
 * Enrollment at a grade the branch doesn't actually teach.
 */
it('refuses to promote when the next grade level exists globally but is not offered at this Enrollment\'s branch', function () {
    $current = activeEnrollmentAt(995);
    // Deliberately no offerGradeAtBranch() call -- this GradeLevel
    // exists, just not at $current->branch_id.
    GradeLevel::factory()->create(['sequence_order' => 996]);
    $nextYear = AcademicYear::factory()->active()->create();

    expect(fn () => app(PromoteEnrollmentAction::class)->execute($current, $nextYear->id))
        ->toThrow(NoNextGradeLevelException::class);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_ACTIVE);
});

it('refuses to promote into a closed Academic Year', function () {
    $current = activeEnrollmentAt(510);
    GradeLevel::factory()->create(['sequence_order' => 511]);
    $closedYear = AcademicYear::factory()->closed()->create();

    expect(fn () => app(PromoteEnrollmentAction::class)->execute($current, $closedYear->id))
        ->toThrow(ClosedAcademicYearException::class);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_ACTIVE);
});

it('promotes an Enrollment whose own Academic Year has already closed -- the normal end-of-year workflow', function () {
    Event::fake([EnrollmentPromoted::class]);

    $branch = Branch::factory()->create();
    $closedYear = AcademicYear::factory()->closed()->create();
    $gradeLevel = GradeLevel::factory()->create(['sequence_order' => 520]);
    offerGradeAtBranch($branch->id, GradeLevel::factory()->create(['sequence_order' => 521]));

    $current = Enrollment::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $closedYear->id,
        'grade_level_id' => $gradeLevel->id,
        'status' => Enrollment::STATUS_ACTIVE,
    ]);

    $nextYear = AcademicYear::factory()->active()->create();

    $next = app(PromoteEnrollmentAction::class)->execute($current, $nextYear->id);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_PROMOTED)
        ->and($next->academic_year_id)->toBe($nextYear->id);
});

it('refuses to promote an Enrollment that is not active', function () {
    $current = activeEnrollmentAt(530);
    offerGradeAtBranch($current->branch_id, GradeLevel::factory()->create(['sequence_order' => 531]));
    $nextYear = AcademicYear::factory()->active()->create();
    app(PromoteEnrollmentAction::class)->execute($current, $nextYear->id);

    $anotherYear = AcademicYear::factory()->active()->create();

    expect(fn () => app(PromoteEnrollmentAction::class)->execute($current->fresh(), $anotherYear->id))
        ->toThrow(EnrollmentNotActiveException::class);
});

// --- Repetition ---------------------------------------------------------

it('repeats an active Enrollment into a new one at the same grade level', function () {
    Event::fake([EnrollmentRepeated::class]);

    $current = activeEnrollmentAt(540);
    $nextYear = AcademicYear::factory()->active()->create();

    $next = app(RepeatEnrollmentAction::class)->execute($current, $nextYear->id);

    expect($next->grade_level_id)->toBe($current->grade_level_id)
        ->and($next->academic_year_id)->toBe($nextYear->id)
        ->and($next->previous_enrollment_id)->toBe($current->id);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_REPEATED)
        ->and($current->fresh()->next_enrollment_id)->toBe($next->id);

    Event::assertDispatched(EnrollmentRepeated::class, fn ($event) => $event->previous->is($current) && $event->next->is($next));
});

it('refuses to repeat an Enrollment that is not active', function () {
    $current = activeEnrollmentAt(550);
    $current->withdraw();

    $nextYear = AcademicYear::factory()->active()->create();

    expect(fn () => app(RepeatEnrollmentAction::class)->execute($current->fresh(), $nextYear->id))
        ->toThrow(EnrollmentNotActiveException::class);
});

// --- Withdrawal ---------------------------------------------------------

it('withdraws an active Enrollment and its Student together', function () {
    Event::fake([EnrollmentWithdrawn::class, StudentWithdrawn::class]);

    $current = activeEnrollmentAt(560);
    $student = Student::find($current->student_id);

    app(WithdrawEnrollmentAction::class)->execute($current);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_WITHDRAWN)
        ->and($student->fresh()->lifecycle_status)->toBe(Student::STATUS_WITHDRAWN)
        ->and($student->fresh()->current_enrollment_id)->toBe($current->id);

    Event::assertDispatched(EnrollmentWithdrawn::class, fn ($event) => $event->enrollment->is($current));
    Event::assertDispatched(StudentWithdrawn::class, fn ($event) => $event->student->is($student));
});

it('refuses to withdraw an Enrollment that is not active', function () {
    $current = activeEnrollmentAt(570);
    app(WithdrawEnrollmentAction::class)->execute($current);

    expect(fn () => app(WithdrawEnrollmentAction::class)->execute($current->fresh()))
        ->toThrow(EnrollmentNotActiveException::class);
});

// --- Graduation ---------------------------------------------------------

it('graduates an active Enrollment at the final grade level, and its Student together', function () {
    Event::fake([EnrollmentGraduated::class, StudentGraduated::class]);

    $current = activeEnrollmentAt(580); // no higher sequence_order created -- this is the final grade
    $student = Student::find($current->student_id);

    app(GraduateEnrollmentAction::class)->execute($current);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_GRADUATED)
        ->and($student->fresh()->lifecycle_status)->toBe(Student::STATUS_GRADUATED);

    Event::assertDispatched(EnrollmentGraduated::class, fn ($event) => $event->enrollment->is($current));
    Event::assertDispatched(StudentGraduated::class, fn ($event) => $event->student->is($student));
});

it('refuses to graduate from a grade level that is not the final one at this branch', function () {
    $current = activeEnrollmentAt(590);
    offerGradeAtBranch($current->branch_id, GradeLevel::factory()->create(['sequence_order' => 591]));

    expect(fn () => app(GraduateEnrollmentAction::class)->execute($current))
        ->toThrow(NotAtFinalGradeLevelException::class);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_ACTIVE);
});

/**
 * Independent Review Finding 1 regression test -- a higher-sequence
 * GradeLevel exists globally (at a different branch), but this
 * Enrollment's own branch offers nothing beyond its current grade.
 * Before the fix, AcademicCatalogService::isFinalGradeLevel() ignored
 * BranchGradeLevel entirely and would have wrongly refused this
 * graduation, since a higher grade existed *somewhere* in the system.
 */
it('graduates successfully at the last grade this branch offers, even though a higher grade exists globally', function () {
    Event::fake([EnrollmentGraduated::class, StudentGraduated::class]);

    $current = activeEnrollmentAt(595);
    // Exists globally, at a different branch -- not offered at
    // $current->branch_id.
    $otherBranch = Branch::factory()->create();
    offerGradeAtBranch($otherBranch->id, GradeLevel::factory()->create(['sequence_order' => 596]));

    app(GraduateEnrollmentAction::class)->execute($current);

    expect($current->fresh()->status)->toBe(Enrollment::STATUS_GRADUATED);
});

it('refuses to graduate an Enrollment that is not active', function () {
    $current = activeEnrollmentAt(600);
    app(GraduateEnrollmentAction::class)->execute($current);

    expect(fn () => app(GraduateEnrollmentAction::class)->execute($current->fresh()))
        ->toThrow(EnrollmentNotActiveException::class);
});

// --- Chain Invariant ---------------------------------------------------------

/**
 * Sprint 4.4 Architecture Pass, explicit user requirement: the chain
 * must never branch or cycle. Proven here across a real multi-step
 * sequence (promote twice), walking the chain in both directions.
 */
it('produces a clean, non-branching chain across multiple promotions', function () {
    $first = activeEnrollmentAt(700);
    offerGradeAtBranch($first->branch_id, GradeLevel::factory()->create(['sequence_order' => 701]));
    offerGradeAtBranch($first->branch_id, GradeLevel::factory()->create(['sequence_order' => 702]));
    $yearTwo = AcademicYear::factory()->active()->create();
    $yearThree = AcademicYear::factory()->active()->create();

    $second = app(PromoteEnrollmentAction::class)->execute($first, $yearTwo->id);
    $third = app(PromoteEnrollmentAction::class)->execute($second, $yearThree->id);

    $first->refresh();
    $second->refresh();
    $third->refresh();

    expect($first->status)->toBe(Enrollment::STATUS_PROMOTED)
        ->and($first->previous_enrollment_id)->toBeNull()
        ->and($first->next_enrollment_id)->toBe($second->id);

    expect($second->status)->toBe(Enrollment::STATUS_PROMOTED)
        ->and($second->previous_enrollment_id)->toBe($first->id)
        ->and($second->next_enrollment_id)->toBe($third->id);

    expect($third->status)->toBe(Enrollment::STATUS_ACTIVE)
        ->and($third->previous_enrollment_id)->toBe($second->id)
        ->and($third->next_enrollment_id)->toBeNull();

    // No branching: no other Enrollment row anywhere points at any of
    // these three as its own previous/next.
    expect(Enrollment::where('previous_enrollment_id', $first->id)->count())->toBe(1)
        ->and(Enrollment::where('next_enrollment_id', $second->id)->count())->toBe(1)
        ->and(Enrollment::where('previous_enrollment_id', $second->id)->count())->toBe(1)
        ->and(Enrollment::where('next_enrollment_id', $third->id)->count())->toBe(1);
});

// --- afterCommit regression tests ---------------------------------------------------------

it('defers EnrollmentPromoted and EnrollmentRepeated until the wrapping transaction commits', function () {
    Event::fake([EnrollmentPromoted::class, EnrollmentRepeated::class]);

    $enrollment = activeEnrollmentAt(800);

    try {
        DB::transaction(function () use ($enrollment): void {
            $enrollment->promote(AcademicYear::factory()->active()->create()->id, GradeLevel::factory()->create(['sequence_order' => 801])->id);

            throw new RuntimeException('forced rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    Event::assertNotDispatched(EnrollmentPromoted::class);
    expect($enrollment->fresh()->status)->toBe(Enrollment::STATUS_ACTIVE);
});

it('defers EnrollmentWithdrawn/StudentWithdrawn until the wrapping transaction commits', function () {
    Event::fake([EnrollmentWithdrawn::class, StudentWithdrawn::class]);

    $enrollment = activeEnrollmentAt(810);
    $student = Student::find($enrollment->student_id);

    try {
        DB::transaction(function () use ($enrollment, $student): void {
            $enrollment->withdraw();
            $student->withdraw();

            throw new RuntimeException('forced rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    Event::assertNotDispatched(EnrollmentWithdrawn::class);
    Event::assertNotDispatched(StudentWithdrawn::class);
    expect($enrollment->fresh()->status)->toBe(Enrollment::STATUS_ACTIVE);
    expect($student->fresh()->lifecycle_status)->toBe(Student::STATUS_ACTIVE);
});
