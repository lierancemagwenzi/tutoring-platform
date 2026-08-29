<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkFinancialTransactionPaidRequest;
use App\Http\Requests\Admin\UpdatePayoutStatusRequest;
use App\Http\Resources\Admin\FinancialTransactionResource;
use App\Models\FinancialTransaction;
use App\Services\Admin\FinancialTransactionReportingService;
use App\Services\Admin\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialTransactionController extends Controller
{
    public function __construct(
        private readonly FinancialTransactionReportingService $transactions,
        private readonly PayoutService $payouts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = $this->transactions->list(
            $request->only(['tutor_profile_id', 'product_type', 'from', 'to']),
            (int) $request->integer('per_page', 15),
        );

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

    public function markPaid(MarkFinancialTransactionPaidRequest $request, FinancialTransaction $financialTransaction): JsonResponse
    {
        $transaction = $this->payouts->markPaid($financialTransaction, $request->user());

        return response()->json(['transaction' => new FinancialTransactionResource($transaction)]);
    }

    public function updatePayoutStatus(UpdatePayoutStatusRequest $request, FinancialTransaction $financialTransaction): JsonResponse
    {
        $transaction = $this->payouts->updateStatus(
            $financialTransaction,
            PayoutStatus::from($request->validated('status')),
            $request->user(),
        );

        return response()->json(['transaction' => new FinancialTransactionResource($transaction)]);
    }
}
