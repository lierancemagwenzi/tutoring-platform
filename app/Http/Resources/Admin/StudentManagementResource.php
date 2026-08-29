<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'disabled' => $this->disabled_at !== null,
            'enrollments_count' => $this->enrollments_count,
            'bookings_count' => $this->bookings_count,
            'registered_at' => $this->created_at->toIso8601String(),
        ];
    }
}
