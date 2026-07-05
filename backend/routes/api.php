<?php

use App\Modules\Media\Http\Controllers\PrivateMediaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Private-tier media is served exclusively through this authenticated
// route, never a raw signed URL -- docs/DOMAIN_BLUEPRINT.md §12.
Route::get('/private-files/{media}', [PrivateMediaController::class, 'show'])
    ->middleware('auth:sanctum')
    ->name('media.private.show');
