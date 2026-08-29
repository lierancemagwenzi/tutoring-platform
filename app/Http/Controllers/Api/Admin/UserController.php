<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Services\Admin\AdminUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly AdminUserManagementService $users) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'role']);

        if ($request->has('verified')) {
            $filters['verified'] = $request->boolean('verified');
        }

        $users = $this->users->list($filters, (int) $request->integer('per_page', 15));

        return response()->json([
            'users' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['user' => new UserResource($user)]);
    }

    public function disable(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot disable your own account.');

        $user = $this->users->disable($user, $request->user());

        return response()->json(['user' => new UserResource($user)]);
    }

    public function enable(Request $request, User $user): JsonResponse
    {
        $user = $this->users->enable($user, $request->user());

        return response()->json(['user' => new UserResource($user)]);
    }
}
