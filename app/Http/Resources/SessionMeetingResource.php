<?php

namespace App\Http\Resources;

use App\Enums\MeetingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionMeetingResource extends JsonResource
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
            'provider' => $this->provider->value,
            'calendar_event_id' => $this->calendar_event_id,
            'meeting_id' => $this->meeting_id,
            'meeting_url' => $this->meeting_url,
            'organizer_email' => $this->organizer_email,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status->value,
            // Why creation failed (see MeetingService::markFailed()) — only
            // meaningful once Failed, so the tutor sees an actionable reason
            // ("...doesn't have calendar permission...") instead of a bare
            // "failed" with no explanation.
            'failure_reason' => $this->status === MeetingStatus::Failed ? ($this->metadata['error'] ?? null) : null,
        ];
    }
}
