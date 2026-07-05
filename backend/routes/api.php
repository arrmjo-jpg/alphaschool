<?php

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
});
