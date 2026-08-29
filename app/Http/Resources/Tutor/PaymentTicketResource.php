<?php

namespace App\Http\Resources\Tutor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transaction = $this->financialTransaction;

        return [
            'id' => $this->id,
            'transaction' => [
                'id' => $transaction->id,
                'product_type' => $transaction->product_type,
                'product_id' => $transaction->product_id,
                'gross_amount' => $transaction->gross_amount,
                'tutor_amount' => $transaction->tutor_amount,
                'currency' => $transaction->currency,
                'payout_status' => $transaction->payout_status->value,
            ],
            'message' => $this->message,
            'status' => $this->status->value,
            'comments_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
