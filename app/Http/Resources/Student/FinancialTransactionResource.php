<?php

namespace App\Http\Resources\Student;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_type' => $this->product_type,
            'product_title' => $this->productTitle(),
            // Native decimal:2 string, not float-cast — see FinancialRuleResource for why.
            'gross_amount' => $this->gross_amount,
            'currency' => $this->currency,
            'refund_status' => $this->refund_status->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function productTitle(): ?string
    {
        $product = $this->product;

        if (! $product) {
            return null;
        }

        return $product instanceof Booking ? $product->service?->title : $product->title;
    }
}
