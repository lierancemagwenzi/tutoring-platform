<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The correct answer is deliberately omitted from the student-facing view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'type' => $this->type,
            'text' => $this->definition['title'] ?? '',
            'choices' => $this->definition['choices'] ?? [],
            'points' => $this->points,
        ];
    }
}
