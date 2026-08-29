<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SystemHealthService;
use Illuminate\Http\JsonResponse;

class SystemHealthController extends Controller
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function index(): JsonResponse
    {
        return response()->json(['checks' => $this->health->check()]);
    }
}
