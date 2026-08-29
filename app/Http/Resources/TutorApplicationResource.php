<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TutorApplicationResource extends JsonResource
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
            'display_name' => $this->display_name,
            'profile_photo_url' => $this->profile_photo ? Storage::disk('public')->url($this->profile_photo) : null,
            'bio' => $this->bio,
            'years_experience' => $this->years_experience,
            'occupation' => $this->occupation,
            'languages' => $this->languages ?? [],
            'teaching_style' => $this->teaching_style,
            'about_me' => $this->about_me,
            'why_choose_me' => $this->why_choose_me,
            'government_id' => $this->government_id_path ? [
                'name' => $this->government_id_name,
                'url' => Storage::disk('public')->url($this->government_id_path),
            ] : null,
            'onboarding_step' => $this->onboarding_step,
            'onboarding_complete' => $this->onboarding_complete,
            'qualifications' => TutorQualificationResource::collection($this->whenLoaded('qualifications')),
            'documents' => TutorDocumentResource::collection($this->whenLoaded('documents')),
            'missing_requirements' => $this->missingSubmissionRequirements(),
        ];
    }
}
