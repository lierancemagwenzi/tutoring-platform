<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->tutorProfile;

        return [
            'id' => $this->id,
            'name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'registered_at' => $this->created_at->toIso8601String(),
            'email_verified' => $this->hasVerifiedEmail(),
            'status' => $this->status->value,
            'profile_complete' => $profile ? empty($profile->missingSubmissionRequirements()) : false,
            'requested_subjects_count' => $profile?->tutor_subjects_count ?? 0,
            'services_count' => $profile?->services_count ?? 0,
            'self_paced_courses_count' => $profile?->self_paced_courses_count ?? 0,
            'has_banking_details' => (bool) $profile?->bankAccount,
        ];
    }
}
