<?php

use App\Core\Models\ReasonCode;
use App\Modules\Admissions\Actions\DecideApplicationAction;
use App\Modules\Admissions\Actions\RecordAssessmentResultAction;
use App\Modules\Admissions\Actions\WithdrawApplicationAction;
use App\Modules\Admissions\Events\ApplicantConverted;
use App\Modules\Admissions\Exceptions\InvalidRejectionReasonException;
use App\Modules\Admissions\Models\Applicant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function applicationRejectionReason(?string $context = null): ReasonCode
{
    $context ??= Applicant::REJECTION_REASON_CONTEXT;

    return ReasonCode::create([
        'context' => $context,
        'code' => 'assessment_below_threshold',
        'label' => ['en' => 'Assessment score below threshold', 'ar' => 'نتيجة الاختبار أقل من الحد المطلوب'],
        'is_active' => true,
    ]);
}

it('walks the full happy path: submitted -> under_review -> tested -> accepted', function () {
    $applicant = Applicant::factory()->create();
    expect($applicant->status)->toBe(Applicant::STATUS_SUBMITTED);

    $assessment = app(RecordAssessmentResultAction::class)->execute($applicant, 85.5, 'Strong performance');

    $applicant->refresh();
    expect($applicant->status)->toBe(Applicant::STATUS_TESTED);
    expect($assessment->score)->toEqual('85.50');
    expect($applicant->assessments()->count())->toBe(1);

    app(DecideApplicationAction::class)->accept($applicant);

    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_ACCEPTED);
});

it('rejects an application with a required reason code', function () {
    $applicant = Applicant::factory()->tested()->create();
    $reason = applicationRejectionReason();

    app(DecideApplicationAction::class)->reject($applicant, $reason->id);

    $applicant->refresh();
    expect($applicant->status)->toBe(Applicant::STATUS_REJECTED);
    expect($applicant->rejection_reason_code_id)->toBe($reason->id);
});

/**
 * Independent Review regression test -- reject() previously accepted
 * any reason_codes.id satisfying the foreign key, with no check it
 * belongs to the application_rejection context.
 */
it('rejects a wrong-context reason code and leaves the Applicant unchanged', function () {
    $applicant = Applicant::factory()->tested()->create();
    $wrongContextReason = applicationRejectionReason('guardian_student_relationship');

    expect(fn () => app(DecideApplicationAction::class)->reject($applicant, $wrongContextReason->id))
        ->toThrow(InvalidRejectionReasonException::class);

    $applicant->refresh();
    expect($applicant->status)->toBe(Applicant::STATUS_TESTED);
    expect($applicant->rejection_reason_code_id)->toBeNull();
});

it('rejects a reason code id that does not exist at all', function () {
    $applicant = Applicant::factory()->tested()->create();

    expect(fn () => app(DecideApplicationAction::class)->reject($applicant, 999999))
        ->toThrow(InvalidRejectionReasonException::class);
});

it('withdraws an application from a non-terminal state', function () {
    $applicant = Applicant::factory()->underReview()->create();

    app(WithdrawApplicationAction::class)->execute($applicant);

    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_WITHDRAWN);
});

it('does not allow a terminal Applicant to transition again', function () {
    $applicant = Applicant::factory()->accepted()->create();

    app(WithdrawApplicationAction::class)->execute($applicant);

    // isTerminal() blocks the transition -- accept() is already
    // terminal, so withdraw() must be a no-op, not an overwrite.
    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_ACCEPTED);
});

it('converts an accepted Applicant to converted, and no-ops on a second direct call', function () {
    $applicant = Applicant::factory()->accepted()->create();

    $applicant->convert();

    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_CONVERTED)
        ->and($applicant->fresh()->converted_at)->not->toBeNull();

    $convertedAt = $applicant->fresh()->converted_at;

    // Sprint 4.3 Technical Specification §9 -- convert() is a defensive
    // backstop behind ConvertApplicantToStudentAction's own upfront
    // check, so a second direct call must no-op, not throw or overwrite
    // converted_at.
    $applicant->fresh()->convert();

    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_CONVERTED)
        ->and($applicant->fresh()->converted_at)->toEqual($convertedAt);
});

/**
 * Independent Review Finding 2 regression test -- convert() previously
 * dispatched ApplicantConverted synchronously, so a caller (e.g.
 * ConvertApplicantToStudentAction) that opens a transaction around
 * convert() and later rolls back would still have already fired the
 * event, unlike StudentEnrolled's own DB::afterCommit() discipline.
 * This forces a rollback after convert() runs and proves the event
 * never fires -- it would have, before the fix.
 */
it('defers ApplicantConverted until the wrapping transaction commits, matching StudentEnrolled\'s own discipline', function () {
    Event::fake([ApplicantConverted::class]);

    $applicant = Applicant::factory()->accepted()->create();

    try {
        DB::transaction(function () use ($applicant): void {
            $applicant->convert();

            throw new RuntimeException('forced rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    Event::assertNotDispatched(ApplicantConverted::class);
    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_ACCEPTED);
});

it('leaves a non-accepted Applicant\'s status unchanged when convert() is called', function () {
    $applicant = Applicant::factory()->tested()->create();

    $applicant->convert();

    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_TESTED);
});

it('leaves an already-tested Applicant\'s status unchanged when another assessment is recorded', function () {
    $applicant = Applicant::factory()->tested()->create();

    app(RecordAssessmentResultAction::class)->execute($applicant, 60.0);

    // moveToReview()/markTested() are both no-ops from 'tested' --
    // status-machine idempotency, matching AcademicYear::close()'s own
    // precedent (Sprint 4.1). The assessment row itself is still
    // created; only the status transition is guarded.
    expect($applicant->fresh()->status)->toBe(Applicant::STATUS_TESTED);
    expect($applicant->assessments()->count())->toBe(1);
});
