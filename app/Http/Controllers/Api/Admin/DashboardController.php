<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboard) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'overview' => $this->dashboard->overview(),
            'action_required' => $this->dashboard->actionRequired(),
        ]);
    }
}
