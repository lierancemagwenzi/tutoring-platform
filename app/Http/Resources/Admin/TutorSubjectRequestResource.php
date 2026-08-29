<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorSubjectRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tutor' => [
                'tutor_profile_id' => $this->tutorProfile->id,
                'display_name' => $this->tutorProfile->display_name,
                'email' => $this->tutorProfile->user->email,
                'profile_status' => $this->tutorProfile->user->status->value,
            ],
            'subject' => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
            ],
            'status' => $this->status->value,
            'requested_at' => $this->created_at->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
