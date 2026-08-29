<?php

namespace App\Http\Resources\Student\SelfPaced;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedAssessmentAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'self_paced_assessment_id' => $this->self_paced_assessment_id,
            'provider' => $this->provider?->value,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'time_taken_seconds' => $this->time_taken_seconds,
            'raw_score' => $this->raw_score,
            'max_score' => $this->max_score,
            'percentage' => $this->percentage,
            'passed' => $this->passed,
        ];
    }
}
