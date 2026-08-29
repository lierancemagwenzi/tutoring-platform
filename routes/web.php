<?php

use App\Http\Controllers\Api\H5p\H5pAjaxController;
use App\Http\Controllers\Api\H5p\H5pAssetController;
use Illuminate\Support\Facades\Route;

// This app authenticates the SPA with Sanctum personal-access (Bearer)
// tokens in localStorage, not session cookies (see resources/js/services/
// api.js) — so:
//   - editor-ajax is gated by 'auth:sanctum', and the Vue widgets configure
//     H5P.jQuery.ajaxSetup() to attach the same Bearer token to the legacy
//     client JS's own ajax calls (it doesn't go through our axios instance,
//     so it wouldn't otherwise carry one). H5pAjaxController itself further
//     enforces 'tutor' for the two actions that install/upload new library
//     code onto the server.
//   - the static asset routes (core/editor/library/content files) are left
//     unauthenticated: a <script src>/<link href>/iframe-written tag has no
//     way to attach a Bearer header, so gating these would just break asset
//     loading outright. These are generic library files and shared content
//     assets, not per-user secrets — the old Node service served its
//     equivalent /h5p/* routes the same way (CORS-restricted, not
//     authenticated). Content itself stays a shared, no-per-tutor-ownership
//     library, matching the model already used elsewhere (see
//     App\Services\H5p\Framework\LaravelH5PFramework::hasPermission()).
// Must be registered before the SPA catch-all below.
Route::prefix('h5p-assets')->group(function () {
    Route::any('editor-ajax/{action}', [H5pAjaxController::class, 'action'])
        ->middleware('auth:sanctum')
        ->name('h5p.editor-ajax');
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
