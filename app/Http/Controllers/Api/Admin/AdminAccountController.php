<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteAdminRequest;
use App\Http\Resources\Admin\AdminAccountResource;
use App\Models\User;
use App\Services\Admin\AdminAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAccountController extends Controller
{
    public function __construct(private readonly AdminAccountService $accounts) {}

    public function index(): JsonResponse
    {
        $admins = User::where('role', UserRole::Admin)->latest()->get();

        return response()->json(['admins' => AdminAccountResource::collection($admins)]);
    }

    public function store(InviteAdminRequest $request): JsonResponse
    {
        $invitee = $this->accounts->invite(
            $request->validated('first_name'),
            $request->validated('last_name'),
            $request->validated('email'),
            $request->user(),
        );

        return response()->json(['admin' => new AdminAccountResource($invitee)], 201);
    }

    public function resendInvite(Request $request, User $user): JsonResponse
    {
        $this->accounts->resendInvite($user, $request->user());

        return response()->json(['message' => 'Invite resent.']);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        $admin = $this->accounts->deactivate($user, $request->user());

        return response()->json(['admin' => new AdminAccountResource($admin)]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $admin = $this->accounts->activate($user, $request->user());

        return response()->json(['admin' => new AdminAccountResource($admin)]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->accounts->delete($user, $request->user());

        return response()->json(['deleted' => true]);
    }
}
