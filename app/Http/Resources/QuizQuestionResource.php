<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizQuestionResource extends JsonResource
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
            'quiz_id' => $this->quiz_id,
            'position' => $this->position,
            'type' => $this->type,
            'text' => $this->definition['title'] ?? '',
            'choices' => $this->definition['choices'] ?? [],
            'correct_answer' => $this->definition['correctAnswer'] ?? null,
            'points' => $this->points,
        ];
    }
}
