<?php

namespace App\Http\Resources\Commerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'currency' => $this->currency?->value,
            'total_amount' => $this->total_amount,
            'discount_amount' => $this->discount_amount,
            'booking_fee_amount' => $this->booking_fee_amount,
            'booking_fee_percentage' => $this->booking_fee_percentage,
            'final_amount' => $this->final_amount,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment' => $this->whenLoaded('latestPayment', fn () => $this->latestPayment ? new PaymentResource($this->latestPayment) : null),
        ];
    }
}
