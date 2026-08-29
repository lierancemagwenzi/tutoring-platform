<?php

namespace App\Http\Resources\Commerce;

use App\Enums\ProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'product_type' => $this->product_type,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'total' => $this->total,
            'product' => $this->whenLoaded('product', fn () => $this->product ? $this->productSummary() : null),
        ];
    }

    /**
     * A minimal, product-type-aware summary — each product type exposes
     * `id` plus whatever "what am I looking at" fields make sense for it.
     *
     * @return array<string, mixed>
     */
    private function productSummary(): array
    {
        return match ($this->product_type) {
            ProductType::TutoringServiceBooking->value => [
                'id' => $this->product->id,
                'title' => $this->product->service?->title ?? 'Tutoring Session',
                'thumbnail_path' => null,
            ],
            default => [
                'id' => $this->product->id,
                'title' => $this->product->title,
                'thumbnail_path' => $this->product->thumbnail_path,
            ],
        };
    }
}
