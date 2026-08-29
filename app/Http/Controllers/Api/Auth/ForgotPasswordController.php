<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    /**
     * Request a password-reset OTP. Always returns the same generic
     * response, whether or not the email belongs to an account — the
     * service silently no-ops for an unknown email.
     */
    public function __invoke(ForgotPasswordRequest $request, PasswordResetService $passwordReset): JsonResponse
    {
        $passwordReset->request($request->validated('email'));

        return response()->json([
            'message' => 'If an account exists for that email address, a password reset code has been sent.',
        ]);
    }
}
