<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaymentManagementResource;
use App\Services\Admin\AdminPaymentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentManagementController extends Controller
{
    public function __construct(private readonly AdminPaymentManagementService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $payments = $this->payments->list($request->only(['status', 'provider']), (int) $request->integer('per_page', 15));

        return response()->json([
            'payments' => PaymentManagementResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
