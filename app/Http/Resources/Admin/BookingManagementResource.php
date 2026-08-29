<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => 'BK-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
            'student' => trim("{$this->student->first_name} {$this->student->last_name}"),
            'tutor' => $this->tutorProfile->display_name,
            'service' => $this->service->title,
            'subject' => $this->service->subject?->name,
            'status' => $this->status->value,
            'payment_status' => $this->order?->latestPayment?->status->value,
            'purchased_sessions' => $this->service->sessions_included,
            'scheduled_sessions' => $this->scheduled_sessions_count,
            'completed_sessions' => $this->completed_sessions_count,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
