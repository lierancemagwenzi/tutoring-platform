<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttemptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The raw provider payload and internal metadata are tutor-only detail
     * (see App\Http\Resources\AttemptResource) — a student just needs their
     * own result.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_lesson_block_id' => $this->session_lesson_block_id,
            'provider' => $this->provider->value,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'time_taken_seconds' => $this->time_taken_seconds,
            'raw_score' => $this->raw_score,
            'max_score' => $this->max_score,
            'percentage' => $this->percentage,
            'passed' => $this->passed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
