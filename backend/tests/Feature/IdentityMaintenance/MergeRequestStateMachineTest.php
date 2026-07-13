<?php

use App\Modules\IdentityMaintenance\Models\MergeRequest;

it('creates a merge request in draft status with a public_id', function () {
    $mergeRequest = MergeRequest::factory()->create();

    expect($mergeRequest->status)->toBe(MergeRequest::STATUS_DRAFT)
        ->and($mergeRequest->public_id)->not->toBeNull();
});

it('allows every documented legal transition', function () {
    $mergeRequest = MergeRequest::factory()->create();

    $mergeRequest->update(['status' => MergeRequest::STATUS_DRY_RUN_PASSED]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_PENDING_APPROVAL]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_APPROVED]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_EXECUTING]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_EXECUTED]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLBACK_REQUESTED]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLBACK_APPROVED]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLING_BACK]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLED_BACK]);

    expect($mergeRequest->fresh()->status)->toBe(MergeRequest::STATUS_ROLLED_BACK);
});

it('allows the failed -> approved retry transition and the rollback re-request transitions', function () {
    $executing = MergeRequest::factory()->create(['status' => MergeRequest::STATUS_EXECUTING]);
    $executing->update(['status' => MergeRequest::STATUS_FAILED]);
    $executing->update(['status' => MergeRequest::STATUS_APPROVED]);
    expect($executing->fresh()->status)->toBe(MergeRequest::STATUS_APPROVED);

    $rollbackRequested = MergeRequest::factory()->create(['status' => MergeRequest::STATUS_ROLLBACK_REQUESTED]);
    $rollbackRequested->update(['status' => MergeRequest::STATUS_ROLLBACK_REJECTED]);
    $rollbackRequested->update(['status' => MergeRequest::STATUS_ROLLBACK_REQUESTED]);
    expect($rollbackRequested->fresh()->status)->toBe(MergeRequest::STATUS_ROLLBACK_REQUESTED);
});

it('rejects an illegal status transition', function () {
    $mergeRequest = MergeRequest::factory()->create(); // draft

    expect(fn () => $mergeRequest->update(['status' => MergeRequest::STATUS_EXECUTED]))
        ->toThrow(RuntimeException::class);
});

it('rejects any transition out of the rollback_failed emergency terminal state', function () {
    $mergeRequest = MergeRequest::factory()->create(['status' => MergeRequest::STATUS_ROLLING_BACK]);
    $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLBACK_FAILED]);

    expect(fn () => $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLED_BACK]))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLBACK_REQUESTED]))
        ->toThrow(RuntimeException::class);
});

it('rejects any transition out of a fully terminal state (rejected, cancelled, rolled_back)', function (string $terminalStatus) {
    $mergeRequest = MergeRequest::factory()->create(['status' => $terminalStatus]);

    expect(fn () => $mergeRequest->update(['status' => MergeRequest::STATUS_DRAFT]))
        ->toThrow(RuntimeException::class);
})->with([
    MergeRequest::STATUS_REJECTED,
    MergeRequest::STATUS_CANCELLED,
    MergeRequest::STATUS_ROLLED_BACK,
]);
