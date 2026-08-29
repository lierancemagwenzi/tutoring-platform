<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to anything requiring a verified account. A JSON 403 with a
 * stable `code` (rather than plain text) lets the SPA reliably distinguish
 * "not verified yet" from any other 403 and redirect to Verify Account,
 * without parsing message strings.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            abort(response()->json([
                'message' => 'Please verify your email address to continue.',
                'code' => 'EMAIL_NOT_VERIFIED',
            ], 403));
        }

        return $next($request);
    }
}
