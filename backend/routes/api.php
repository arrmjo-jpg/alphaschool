<?php

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\WorkspaceController;
use App\Modules\IdentityMaintenance\Http\Controllers\MergeRequestController;
use App\Modules\Media\Http\Controllers\PrivateMediaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Private-tier media is served exclusively through this authenticated
// route, never a raw signed URL -- docs/DOMAIN_BLUEPRINT.md §12.
// Versioned per Addendum B7 ("/api/v1 from day one") -- the pre-existing
// unversioned /user route above predates that decision (Sprint 0.1,
// frozen) and is out of scope for this sprint's review, but new routes
// added from Sprint 1.3 onward must comply from the start.
Route::prefix('v1')->group(function () {
    Route::get('/private-files/{media}', [PrivateMediaController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('media.private.show');

    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    // Admin Platform Foundation's backend prerequisite slice (ADR-0015
    // Decision 7) -- the shell's only two required backend surfaces.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [MeController::class, 'show'])->name('me');
        Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    });

    // Sprint 3.2 -- a functional admin surface only (Scope-Out: "Merge
    // UI polish beyond a functional admin screen"). Every write action
    // is Policy-gated inside the controller; step-level approval
    // eligibility is ApprovalEngine's own job.
    Route::middleware('auth:sanctum')->prefix('merge-requests')->name('merge-requests.')->group(function () {
        Route::post('/', [MergeRequestController::class, 'store'])->name('store');
        Route::get('/{mergeRequest}/preview', [MergeRequestController::class, 'preview'])->name('preview');
        Route::post('/{mergeRequest}/dry-run', [MergeRequestController::class, 'dryRun'])->name('dry-run');
        Route::post('/{mergeRequest}/request-approval', [MergeRequestController::class, 'requestApproval'])->name('request-approval');
        Route::post('/{mergeRequest}/approve', [MergeRequestController::class, 'approve'])->name('approve');
        Route::post('/{mergeRequest}/reject', [MergeRequestController::class, 'reject'])->name('reject');
        Route::post('/{mergeRequest}/execute', [MergeRequestController::class, 'execute'])->name('execute');
        Route::post('/{mergeRequest}/request-rollback', [MergeRequestController::class, 'requestRollback'])->name('request-rollback');
        Route::post('/{mergeRequest}/approve-rollback', [MergeRequestController::class, 'approveRollback'])->name('approve-rollback');
        Route::post('/{mergeRequest}/reject-rollback', [MergeRequestController::class, 'rejectRollback'])->name('reject-rollback');
        Route::post('/{mergeRequest}/rollback', [MergeRequestController::class, 'rollback'])->name('rollback');
    });
});
