<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'role' => $this->role,
            'status' => $this->status,
            'is_super_admin' => $this->is_super_admin,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'tutor_profile' => $this->whenLoaded('tutorProfile', fn () => [
                'id' => $this->tutorProfile->id,
                'bio' => $this->tutorProfile->bio,
                'profile_photo' => $this->tutorProfile->profile_photo,
                'onboarding_step' => $this->tutorProfile->onboarding_step,
                'onboarding_complete' => $this->tutorProfile->onboarding_complete,
            ]),
            'student_guardian' => $this->whenLoaded('studentGuardian', fn () => $this->studentGuardian ? [
                'guardian_first_name' => $this->studentGuardian->guardian_first_name,
                'guardian_last_name' => $this->studentGuardian->guardian_last_name,
                'guardian_email' => $this->studentGuardian->guardian_email,
                'guardian_phone' => $this->studentGuardian->guardian_phone,
                'relationship_to_student' => $this->studentGuardian->relationship_to_student,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
