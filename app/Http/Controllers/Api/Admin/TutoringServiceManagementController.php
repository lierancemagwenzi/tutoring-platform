<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TutoringServiceManagementResource;
use App\Services\Admin\AdminTutoringServiceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutoringServiceManagementController extends Controller
{
    public function __construct(private readonly AdminTutoringServiceManagementService $services) {}

    public function index(Request $request): JsonResponse
    {
        $services = $this->services->list($request->only(['visibility', 'search']), (int) $request->integer('per_page', 15));

        return response()->json([
            'services' => TutoringServiceManagementResource::collection($services->items()),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]);
    }
}
