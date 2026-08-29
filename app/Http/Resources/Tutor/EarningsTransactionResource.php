<?php

namespace App\Http\Resources\Tutor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The tutor-facing view of a FinancialTransaction — deliberately narrower
 * than the admin resource: a tutor sees their own gross/amount, not the
 * platform's internal fee-line breakdown.
 */
class EarningsTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_type' => $this->product_type,
            // Native decimal:2 strings, not float-cast — see FinancialRuleResource for why.
            'gross_amount' => $this->gross_amount,
            'tutor_amount' => $this->tutor_amount,
            'currency' => $this->currency,
            'payout_status' => $this->payout_status->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refund_status' => $this->refund_status->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
