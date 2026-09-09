<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AccountVerificationService;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    /**
     * Verify the authenticated user's account using the submitted OTP.
     */
    public function __invoke(VerifyEmailRequest $request, AccountVerificationService $verification): JsonResponse
    {
        $verification->verify($request->user(), $request->validated('otp'));

        return response()->json([
            'message' => 'Your account has been verified.',
            'user' => new UserResource($request->user()->fresh(['tutorProfile', 'studentGuardian'])),
        ]);
    }
}
