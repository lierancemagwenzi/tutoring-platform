<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptAdminInviteRequest;
use App\Services\Auth\AdminInvitationAcceptanceService;
use Illuminate\Http\JsonResponse;

class AcceptAdminInviteController extends Controller
{
    /**
     * Verify the invite OTP and set the admin's password. Does not require
     * authentication — this is how an invited admin gets their account.
     */
    public function __invoke(AcceptAdminInviteRequest $request, AdminInvitationAcceptanceService $acceptance): JsonResponse
    {
        $acceptance->accept(
            $request->validated('email'),
            $request->validated('otp'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Your account is ready. You can now log in.',
        ]);
    }
}
