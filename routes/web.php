<?php

use App\Http\Controllers\Api\H5p\H5pAjaxController;
use App\Http\Controllers\Api\H5p\H5pAssetController;
use Illuminate\Support\Facades\Route;

// H5P core/editor client JS loads these directly (same-origin, so ordinary
// session cookies apply). Gated by plain session auth (not 'tutor') since
// both tutors and students need read access — playback/translations/library
// assets aren't tutor-only; H5pAjaxController itself enforces 'tutor' for
// the two actions that install/upload new library code onto the server.
// Content itself stays a shared, no-per-tutor-ownership library, matching
// the model already used elsewhere (see App\Services\H5p\Framework\
// LaravelH5PFramework::hasPermission()). Must be registered before the SPA
// catch-all below.
Route::prefix('h5p-assets')->middleware('auth')->group(function () {
    Route::any('editor-ajax/{action}', [H5pAjaxController::class, 'action'])->name('h5p.editor-ajax');
    Route::get('libraries/{library}/{file}', [H5pAssetController::class, 'library'])
        ->where('file', '.*')
        ->name('h5p.libraries.file');
    Route::get('content/{contentId}/{file}', [H5pAssetController::class, 'content'])
        ->where('file', '.*')
        ->name('h5p.content.file');
    Route::get('core/{file}', [H5pAssetController::class, 'core'])
        ->where('file', '.*')
        ->name('h5p.core.file');
    Route::get('editor/{file}', [H5pAssetController::class, 'editor'])
        ->where('file', '.*')
        ->name('h5p.editor.file');
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
