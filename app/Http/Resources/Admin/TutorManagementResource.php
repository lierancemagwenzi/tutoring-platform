<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'email' => $this->user->email,
            'approval_status' => $this->user->status->value,
            'email_verified' => $this->user->hasVerifiedEmail(),
            'services_count' => $this->services_count,
            'self_paced_courses_count' => $this->self_paced_courses_count,
            'bookings_count' => $this->bookings_count,
            'registered_at' => $this->created_at->toIso8601String(),
        ];
    }
}
