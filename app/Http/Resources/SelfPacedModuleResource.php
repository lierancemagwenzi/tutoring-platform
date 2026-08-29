<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Activities and Assessments are returned as separate arrays, each
     * already sorted by their shared position — the frontend interleaves
     * them by concatenating and sorting on `position` (see
     * SelfPacedModule::orderedContent() for the same merge server-side).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'self_paced_course_id' => $this->self_paced_course_id,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'activity_completion_required' => $this->activity_completion_required,
            'assessment_completion_required' => $this->assessment_completion_required,
            'activities' => SelfPacedActivityResource::collection($this->whenLoaded('activities')),
            'assessments' => SelfPacedAssessmentResource::collection($this->whenLoaded('assessments')),
        ];
    }
}
