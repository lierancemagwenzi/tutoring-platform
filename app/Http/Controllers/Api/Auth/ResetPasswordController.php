<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class ResetPasswordController extends Controller
{
    /**
     * Verify the OTP and set a new password. Does not require
     * authentication — this is how a locked-out user regains access.
     */
    public function __invoke(ResetPasswordRequest $request, PasswordResetService $passwordReset): JsonResponse
    {
        $passwordReset->reset(
            $request->validated('email'),
            $request->validated('otp'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Your password has been reset. You can now log in.',
        ]);
    }
}
