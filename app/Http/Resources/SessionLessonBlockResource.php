<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionLessonBlockResource extends JsonResource
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
            'session_lesson_id' => $this->session_lesson_id,
            'lesson_block_id' => $this->lesson_block_id,
            'availability_mode' => $this->availability_mode->value,
            'available_from' => $this->available_from,
            'available_until' => $this->available_until,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'is_manually_released' => $this->is_manually_released,
            'is_available' => $this->isAvailable(),
            'completion_mode' => $this->completion_mode->value,
            'completion_rule' => $this->completion_rule?->value,
            'attempts_mode' => $this->attempts_mode->value,
            'max_attempts' => $this->max_attempts,
            'passing_score' => $this->passing_score,
            'visibility' => $this->visibility->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
