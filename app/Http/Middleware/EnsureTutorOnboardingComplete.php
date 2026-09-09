<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a tutor from the rest of the API until they've completed their
 * application (basic info, professional profile, qualifications, identity
 * document — see SubmitTutorApplicationController) — a server-side backstop
 * for the SPA's own onboarding redirect (resources/js/router/index.js),
 * which can't be relied on alone since it's bypassable by calling the API
 * directly. Deliberately excluded from the tutor/application/* routes
 * themselves, which is where a tutor completes it — see routes/api.php.
 */
class EnsureTutorOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->role === UserRole::Tutor && $user->tutorProfile?->onboarding_complete !== true) {
            abort(response()->json([
                'message' => 'Please complete your tutor application before continuing.',
                'code' => 'TUTOR_ONBOARDING_INCOMPLETE',
            ], 403));
        }

        return $next($request);
    }
}
