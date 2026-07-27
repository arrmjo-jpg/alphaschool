<?php

use App\Core\Models\ReasonCode;
use App\Modules\Admissions\Actions\DecideApplicationAction;
use App\Modules\Admissions\Actions\RecordAssessmentResultAction;
use App\Modules\Admissions\Actions\WithdrawApplicationAction;
use App\Modules\Admissions\Exceptions\InvalidRejectionReasonException;
use App\Modules\Admissions\Models\Applicant;

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
