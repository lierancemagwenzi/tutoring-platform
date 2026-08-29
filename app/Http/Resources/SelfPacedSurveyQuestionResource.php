<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedSurveyQuestionResource extends JsonResource
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
            'self_paced_survey_content_id' => $this->self_paced_survey_content_id,
            'position' => $this->position,
            'type' => $this->type->value,
            'text' => $this->definition['title'] ?? '',
            'choices' => $this->definition['choices'] ?? [],
            'correct_answer' => $this->definition['correctAnswer'] ?? null,
            'points' => $this->points,
        ];
    }
}
