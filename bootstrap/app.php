<?php

use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureTutorOnboardingComplete;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\EnsureUserIsTutor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'tutor.onboarded' => EnsureTutorOnboardingComplete::class,
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

        // This app is a pure SPA with no server-rendered "login" route at
        // all. Laravel's default Authenticate middleware, when it decides a
        // request doesn't "expect JSON" (jQuery-driven ajax calls — like the
        // H5P editor's own client JS — don't reliably send an Accept:
        // application/json header the way our axios instance does), tries
        // to build a redirect to route('login') *while constructing* the
        // AuthenticationException — which throws RouteNotFoundException
        // before the exception even exists, turning an expired/missing
        // token into a 500 instead of a clean 401. Registering this
        // callback (redirectUsing under the hood) makes redirectTo() return
        // null instead of ever calling route('login'), so the
        // AuthenticationException below can actually be constructed and
        // rendered.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every request here is API-style, so an unauthenticated request
        // should always get a clean JSON 401 (see the redirectGuestsTo()
        // comment above for why this can't just rely on content negotiation).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 401);
        });
    })->create();
