<?php

use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\EnsureUserIsTutor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tutor' => EnsureUserIsTutor::class,
            'admin' => EnsureUserIsAdmin::class,
            'super_admin' => EnsureUserIsSuperAdmin::class,
            'verified' => EnsureEmailVerified::class,
        ]);

        // The H5P editor's own client-side JS (vendor/h5p/h5p-editor/scripts/)
        // posts its internal ajax actions directly via jQuery with no hook for
        // attaching Laravel's CSRF header — H5P has its own per-action security
        // token (see H5PEditorAjaxInterface::validateEditorToken(), driven by
        // H5PCore::createToken()) that serves the same purpose here.
        $middleware->validateCsrfTokens(except: [
            'h5p-assets/editor-ajax/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
