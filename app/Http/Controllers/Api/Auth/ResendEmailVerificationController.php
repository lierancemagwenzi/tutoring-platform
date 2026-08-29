<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccountVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResendEmailVerificationController extends Controller
{
    /**
     * Resend the account-verification OTP to the authenticated user,
     * subject to the resend cooldown enforced by AccountVerificationService.
     */
    public function __invoke(Request $request, AccountVerificationService $verification): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your account is already verified.',
            ], 422);
        }

        $verification->resend($user);

        return response()->json([
            'message' => 'A new verification code has been sent to your email address.',
        ]);
    }
}
