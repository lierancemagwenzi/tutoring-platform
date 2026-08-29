<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\FinancialTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialTransactionController extends Controller
{
    /**
     * The logged in student's own payment history — one row per purchased
     * item (a single order can span several), not per order.
     */
    public function index(Request $request): JsonResponse
    {
        $transactions = $request->user()->financialTransactions()
            ->with('product')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('to')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json([
            'transactions' => FinancialTransactionResource::collection($transactions->items()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
