<?php

use App\Http\Controllers\Api\H5p\H5pAjaxController;
use App\Http\Controllers\Api\H5p\H5pAssetController;
use Illuminate\Support\Facades\Route;

// H5P core/editor client JS loads these directly (same-origin, so ordinary
// session cookies apply) — not behind the 'tutor'/'auth:sanctum' API
// middleware, matching the shared-library, no-ownership-at-this-layer model
// already used for H5P content elsewhere (see App\Services\H5p\Framework\
// LaravelH5PFramework::hasPermission()). Must be registered before the SPA
// catch-all below.
Route::prefix('h5p-assets')->group(function () {
    Route::any('editor-ajax/{action}', [H5pAjaxController::class, 'action'])->name('h5p.editor-ajax');
    Route::get('libraries/{library}/{file}', [H5pAssetController::class, 'library'])
        ->where('file', '.*')
        ->name('h5p.libraries.file');
    Route::get('content/{contentId}/{file}', [H5pAssetController::class, 'content'])
        ->where('file', '.*')
        ->name('h5p.content.file');
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
