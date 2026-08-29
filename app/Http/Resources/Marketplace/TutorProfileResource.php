<?php

namespace App\Http\Resources\Marketplace;

use App\Http\Resources\ServiceResource;
use App\Http\Resources\TutorQualificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subjects = $this->publishedServices->pluck('subject')->filter()->unique('id')->values();

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'profile_photo' => $this->profile_photo,
            'bio' => $this->bio,
            'years_experience' => $this->years_experience,
            'languages' => $this->languages ?? [],
            'qualifications' => TutorQualificationResource::collection($this->whenLoaded('qualifications')),
            'subjects' => $subjects->map(fn ($subject) => ['id' => $subject->id, 'name' => $subject->name])->values(),
            'services' => ServiceResource::collection($this->publishedServices),
        ];
    }
}
