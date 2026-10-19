<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a tutor applicant from their application until their connection
 * has passed the registration speed test (see SpeedTestController) — the
 * server-side backstop for the SPA's own redirect to the speed test step.
 * Gating the application is enough to gate the whole platform, since
 * EnsureTutorOnboardingComplete blocks everything else until it's submitted.
 */
class EnsureTutorPassedSpeedTest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->role === UserRole::Tutor && ! $user->tutorProfile?->speed_test_passed_at) {
            abort(response()->json([
                'message' => 'Please complete the internet speed test before continuing.',
                'code' => 'TUTOR_SPEED_TEST_REQUIRED',
            ], 403));
        }

        return $next($request);
    }
}
