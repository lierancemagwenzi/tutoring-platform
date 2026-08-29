<?php

namespace App\Http\Resources\Admin;

use App\Enums\ProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->order?->items->first();

        return [
            'id' => $this->id,
            'payment_reference' => $this->payment_reference,
            'transaction_reference' => $this->provider_reference,
            'order_number' => $this->order?->order_number,
            'purchase' => $this->purchaseDescription($item),
            'student' => $this->order?->student ? trim("{$this->order->student->first_name} {$this->order->student->last_name}") : null,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function purchaseDescription($item): ?string
    {
        if (! $item) {
            return null;
        }

        return match ($item->product_type) {
            ProductType::CourseOffering->value => $item->product?->title,
            ProductType::TutoringServiceBooking->value => $item->product?->service?->title,
            default => null,
        };
    }
}
