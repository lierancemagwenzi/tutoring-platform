<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
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
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency->value,
            'session_duration_minutes' => $this->session_duration_minutes,
            'sessions_included' => $this->sessions_included,
            'validity_period_days' => $this->validity_period_days,
            'max_students_per_session' => $this->max_students_per_session,
            'visibility' => $this->visibility->value,
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'grade' => new GradeResource($this->whenLoaded('grade')),
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'session_format' => new SessionFormatResource($this->whenLoaded('sessionFormat')),
            'learning_resources' => LearningResourceResource::collection($this->whenLoaded('learningResources')),
            'assessment_types' => AssessmentTypeResource::collection($this->whenLoaded('assessmentTypes')),
            'curricula' => CurriculumResource::collection($this->whenLoaded('curricula')),
            'created_at' => $this->created_at,
        ];
    }
}
