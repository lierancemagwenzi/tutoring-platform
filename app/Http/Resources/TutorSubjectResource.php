<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorSubjectResource extends JsonResource
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
            'subject_id' => $this->subject_id,
            'status' => $this->status->value,
            'grades' => GradeResource::collection($this->grades->sortBy('level')->values()),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
        ];
    }
}
