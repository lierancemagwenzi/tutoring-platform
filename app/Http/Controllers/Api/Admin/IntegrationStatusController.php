<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\IntegrationStatusService;
use Illuminate\Http\JsonResponse;

class IntegrationStatusController extends Controller
{
    public function __construct(private readonly IntegrationStatusService $integrations) {}

    public function index(): JsonResponse
    {
        return response()->json(['integrations' => $this->integrations->status()]);
    }
}
