<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingSessionResource extends JsonResource
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
            'date' => $this->date->format('Y-m-d'),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'status' => $this->status->value,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'tutor_notes' => $this->tutor_notes,
            'capacity' => $this->service->max_students_per_session,
            'participants_count' => $this->bookings->where('status', BookingStatus::Confirmed)->count(),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'meeting' => $this->whenLoaded(
                'sessionMeeting',
                fn () => $this->sessionMeeting ? new SessionMeetingResource($this->sessionMeeting) : null,
            ),
            'lessons' => $this->whenLoaded(
                'sessionLessons',
                fn () => $this->sessionLessons->pluck('lesson.title')->filter()->values(),
            ),
            'bookings' => BookingParticipantResource::collection($this->whenLoaded('bookings')),
        ];
    }
}
