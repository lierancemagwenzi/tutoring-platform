<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tutor\EarningsTransactionResource;
use App\Services\Commerce\TutorEarningsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    public function __construct(private readonly TutorEarningsService $earnings) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->earnings->summary($request->user()->tutorProfile));
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $this->earnings->transactions($request->user()->tutorProfile, (int) $request->integer('per_page', 15));

        return response()->json([
            'transactions' => EarningsTransactionResource::collection($transactions->items()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
