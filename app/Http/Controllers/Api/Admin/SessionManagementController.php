<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SessionManagementResource;
use App\Services\Admin\AdminSessionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionManagementController extends Controller
{
    public function __construct(private readonly AdminSessionManagementService $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = $this->sessions->list($request->only(['status']), (int) $request->integer('per_page', 15));

        return response()->json([
            'sessions' => SessionManagementResource::collection($sessions->items()),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }
}
