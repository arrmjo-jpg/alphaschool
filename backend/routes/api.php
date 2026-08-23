<?php

use App\Core\Http\Controllers\ReasonCodeController;
use App\Modules\Academic\Http\Controllers\AcademicYearController;
use App\Modules\Academic\Http\Controllers\GradeLevelController;
use App\Modules\Academic\Http\Controllers\HomeroomAssignmentController;
use App\Modules\Academic\Http\Controllers\SectionController;
use App\Modules\Academic\Http\Controllers\SubjectController;
use App\Modules\Academic\Http\Controllers\TermController;
use App\Modules\Administration\Http\Controllers\ConfigurationController;
use App\Modules\Administration\Http\Controllers\ProviderRegistryController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\BranchController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\WorkspaceController;
use App\Modules\IdentityMaintenance\Http\Controllers\MergeRequestController;
use App\Modules\Media\Http\Controllers\PrivateMediaController;
use App\Modules\People\Http\Controllers\EmployeeController;
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
        // UI Sprint 1-B (docs/ADMIN_DESIGN_SYSTEM.md §28.17) -- a minimal
        // read-only lookup, not a Branch CRUD workspace; Section is the
        // first Academic entity with a real Branch FK.
        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        // UI Sprint 2 (docs/ADMIN_DESIGN_SYSTEM.md §30.5) -- minimal
        // read-only lookups, not full Employee/ReasonCode CRUD surfaces;
        // HomeroomAssignment's Create form is the first real consumer of
        // both.
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/reason-codes', [ReasonCodeController::class, 'index'])->name('reason-codes.index');
    });

    // Phase E-B (docs/ADMIN_DESIGN_SYSTEM.md §26.13) -- a thin adapter
    // over SettingsResolver/ConfigurationDefinition, no new business
    // logic. View-gating is coarse (bypasses for is_super_admin,
    // matching WorkspaceAccessResolver above); the write endpoint
    // defers entirely to SettingsResolver::write()'s own strict,
    // unmodified permission check -- see ConfigurationController's own
    // docblock for why the two are deliberately not the same bypass.
    Route::middleware('auth:sanctum')->prefix('administration/configuration')->name('administration.configuration.')->group(function () {
        Route::get('/categories', [ConfigurationController::class, 'categories'])->name('categories');
        Route::get('/categories/{capability}/settings', [ConfigurationController::class, 'categorySettings'])->name('categories.settings');
        Route::patch('/categories/{capability}/settings/{key}', [ConfigurationController::class, 'writeSetting'])
            ->where('key', '.*')
            ->name('categories.settings.write');
    });

    // Phase F-B (docs/ADMIN_DESIGN_SYSTEM.md §27.13) -- a thin adapter
    // over ProviderManager/ProviderCredentialVault/HealthCheckRunner, no
    // new business logic. Unlike Configuration Platform, view-gating has
    // no per-slot permission to check at all (§27.2/§27.6 -- no
    // is_super_admin bypass needed because there is nothing to bypass);
    // the test and write endpoints both defer to the exact same
    // required_permission_to_edit check, with no bypass, matching
    // ProviderCredentialVault::assertCanEdit()'s own strict behavior.
    Route::middleware('auth:sanctum')->prefix('administration/providers')->name('administration.providers.')->group(function () {
        Route::get('/', [ProviderRegistryController::class, 'slots'])->name('index');
        Route::get('/{slotKey}', [ProviderRegistryController::class, 'slot'])->where('slotKey', '.*')->name('show');
        Route::post('/{slotKey}/test', [ProviderRegistryController::class, 'testCredentials'])->where('slotKey', '.*')->name('test');
        Route::patch('/{slotKey}', [ProviderRegistryController::class, 'writeCredentials'])->where('slotKey', '.*')->name('write');
    });

    // UI Sprint 1-B (docs/ADMIN_DESIGN_SYSTEM.md §28.17) -- thin adapters
    // over each Academic Master Data model, sharing
    // AcademicMasterDataController's one generic list/get/create/update/
    // setStatus implementation (no per-entity duplication). No delete
    // route exists on any of these groups -- §28.7's binding domain
    // rule ("Reference Master Data entities never expose a Delete
    // action") is enforced structurally, not just by convention: the
    // shared base controller has no destroy() method to route to.
    Route::middleware('auth:sanctum')->prefix('academic')->name('academic.')->group(function () {
        Route::prefix('academic-years')->name('academic-years.')->group(function () {
            Route::get('/', [AcademicYearController::class, 'index'])->name('index');
            Route::get('/{id}', [AcademicYearController::class, 'show'])->name('show');
            Route::post('/', [AcademicYearController::class, 'store'])->name('store');
            Route::patch('/{id}', [AcademicYearController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [AcademicYearController::class, 'setStatus'])->name('status');
        });

        Route::prefix('grade-levels')->name('grade-levels.')->group(function () {
            Route::get('/', [GradeLevelController::class, 'index'])->name('index');
            Route::get('/{id}', [GradeLevelController::class, 'show'])->name('show');
            Route::post('/', [GradeLevelController::class, 'store'])->name('store');
            Route::patch('/{id}', [GradeLevelController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [GradeLevelController::class, 'setStatus'])->name('status');
        });

        Route::prefix('subjects')->name('subjects.')->group(function () {
            Route::get('/', [SubjectController::class, 'index'])->name('index');
            Route::get('/{id}', [SubjectController::class, 'show'])->name('show');
            Route::post('/', [SubjectController::class, 'store'])->name('store');
            Route::patch('/{id}', [SubjectController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [SubjectController::class, 'setStatus'])->name('status');
        });

        Route::prefix('terms')->name('terms.')->group(function () {
            Route::get('/', [TermController::class, 'index'])->name('index');
            Route::get('/{id}', [TermController::class, 'show'])->name('show');
            Route::post('/', [TermController::class, 'store'])->name('store');
            Route::patch('/{id}', [TermController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [TermController::class, 'setStatus'])->name('status');
        });

        Route::prefix('sections')->name('sections.')->group(function () {
            Route::get('/', [SectionController::class, 'index'])->name('index');
            Route::get('/{id}', [SectionController::class, 'show'])->name('show');
            Route::post('/', [SectionController::class, 'store'])->name('store');
            Route::patch('/{id}', [SectionController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [SectionController::class, 'setStatus'])->name('status');
        });

        // UI Sprint 2 (docs/ADMIN_DESIGN_SYSTEM.md §30.6) -- Timeline/
        // Action-shaped, not a thin adapter over AcademicMasterDataController:
        // no update/destroy routes exist at all, since HasTemporalAssignment
        // forbids editing a closed period in place. close/cancel are
        // distinct, separately-named actions (§30.4), not a generic status
        // PATCH.
        Route::prefix('homeroom-assignments')->name('homeroom-assignments.')->group(function () {
            Route::get('/', [HomeroomAssignmentController::class, 'index'])->name('index');
            Route::post('/', [HomeroomAssignmentController::class, 'store'])->name('store');
            Route::patch('/{id}/close', [HomeroomAssignmentController::class, 'close'])->name('close');
            Route::patch('/{id}/cancel', [HomeroomAssignmentController::class, 'cancel'])->name('cancel');
        });
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
