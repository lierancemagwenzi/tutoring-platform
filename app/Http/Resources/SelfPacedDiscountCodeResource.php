<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedDiscountCodeResource extends JsonResource
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
            'self_paced_course_id' => $this->self_paced_course_id,
            'code' => $this->code,
            'discount_type' => $this->discount_type->value,
            'discount_value' => $this->discount_value,
            'max_redemptions' => $this->max_redemptions,
            'times_redeemed' => $this->times_redeemed,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'active' => $this->active,
            'is_valid_now' => $this->isValidNow(),
            'created_at' => $this->created_at,
        ];
    }
}
