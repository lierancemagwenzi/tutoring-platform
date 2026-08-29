<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedAssessmentResource extends JsonResource
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
            'self_paced_module_id' => $this->self_paced_module_id,
            'assessment_type' => $this->assessment_type->value,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'required' => $this->required,
            'passing_score' => $this->passing_score,
            'attempts_mode' => $this->attempts_mode->value,
            'max_attempts' => $this->max_attempts,
            'time_limit_minutes' => $this->time_limit_minutes,
            'available_from' => $this->available_from,
            'available_until' => $this->available_until,
            'randomize_questions' => $this->randomize_questions,
            'show_results' => $this->show_results,
            'show_correct_answers' => $this->show_correct_answers,
            'weight' => $this->weight,
            'provider' => $this->provider?->value,
            'provider_config' => $this->provider_config,
            'is_configured' => $this->isConfigured(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
