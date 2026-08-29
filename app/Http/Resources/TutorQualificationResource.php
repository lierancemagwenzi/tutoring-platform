<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorQualificationResource extends JsonResource
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
            'title' => $this->title,
            'level' => $this->level,
            'field_of_study' => $this->field_of_study,
            'institution' => $this->institution,
            'start_year' => $this->start_year,
            'completion_year' => $this->completion_year,
            'is_currently_studying' => $this->is_currently_studying,
            'description' => $this->description,
        ];
    }
}
