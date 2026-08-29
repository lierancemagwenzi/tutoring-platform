<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tutor' => $this->tutorProfile->display_name,
            'service' => $this->service->title,
            'students' => $this->bookings->map(fn ($booking) => trim("{$booking->student->first_name} {$booking->student->last_name}"))->values(),
            'bookings' => $this->bookings->pluck('id')->values(),
            'lessons' => $this->sessionLessons->map(fn ($sessionLesson) => $sessionLesson->lesson?->title)->filter()->values(),
            'date' => $this->date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status->value,
            'meeting_provider' => $this->sessionMeeting?->provider?->value,
            'meeting_status' => $this->sessionMeeting?->status?->value,
        ];
    }
}
