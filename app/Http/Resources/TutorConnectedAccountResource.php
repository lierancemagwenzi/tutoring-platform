<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorConnectedAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Deliberately excludes access_token/refresh_token/provider_user_id —
     * tokens must never reach the frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider?->value,
            'email' => $this->email,
            'connected_at' => $this->connected_at?->toIso8601String(),
        ];
    }
}
