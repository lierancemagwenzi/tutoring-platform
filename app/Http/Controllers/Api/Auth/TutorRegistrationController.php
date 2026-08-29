<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TutorRegistrationRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AccountVerificationService;
use Illuminate\Http\JsonResponse;

class TutorRegistrationController extends Controller
{
    /**
     * Register a new tutor with an auto-approved status and an empty tutor
     * profile, and issue a verification OTP. The account exists but stays
     * unverified until that code is confirmed — a token is issued now so
     * the frontend can immediately call the (auth-only) verify/resend
     * endpoints without a separate login step.
     */
    public function __invoke(TutorRegistrationRequest $request, AccountVerificationService $verification): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => UserRole::Tutor,
            'status' => UserStatus::Approved,
        ]);

        $user->tutorProfile()->create([]);

        $verification->issueOtp($user);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('tutorProfile')),
            'token' => $token,
            'message' => 'Registration successful. Please verify your email address.',
        ], 201);
    }
}
