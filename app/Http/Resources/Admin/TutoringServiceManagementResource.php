<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutoringServiceManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'visibility' => $this->visibility->value,
            'tutor' => $this->tutorProfile->display_name,
            'subject' => $this->subject?->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'sessions_included' => $this->sessions_included,
            'bookings_count' => $this->bookings_count,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
