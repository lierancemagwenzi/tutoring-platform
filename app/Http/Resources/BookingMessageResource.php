<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'sender' => [
                'id' => $this->sender->id,
                'name' => trim("{$this->sender->first_name} {$this->sender->last_name}"),
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
