<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'audience' => $this->audience->value,
            'is_published' => $this->is_published,
            'position' => $this->position,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
