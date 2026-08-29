<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StudentRegistrationRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AccountVerificationService;
use Illuminate\Http\JsonResponse;

class StudentRegistrationController extends Controller
{
    /**
     * Register a new student, creating a guardian record if the student is
     * under 18, and issue a verification OTP. The account exists but stays
     * unverified until that code is confirmed — a token is issued now so
     * the frontend can immediately call the (auth-only) verify/resend
     * endpoints without a separate login step.
     */
    public function __invoke(StudentRegistrationRequest $request, AccountVerificationService $verification): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::Student,
            'status' => UserStatus::Pending,
        ]);

        if ($request->isMinor()) {
            $user->studentGuardian()->create([
                'guardian_first_name' => $data['guardian_first_name'],
                'guardian_last_name' => $data['guardian_last_name'],
                'guardian_email' => $data['guardian_email'],
                'guardian_phone' => $data['guardian_phone'],
                'relationship_to_student' => $data['relationship_to_student'],
            ]);
        }

        $verification->issueOtp($user);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('studentGuardian')),
            'token' => $token,
            'message' => 'Registration successful. Please verify your email address.',
        ], 201);
    }
}
