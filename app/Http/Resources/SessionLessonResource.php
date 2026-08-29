<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionLessonResource extends JsonResource
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
            'teaching_session_id' => $this->teaching_session_id,
            'position' => $this->position,
            'lesson' => [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
                'description' => $this->lesson->description,
                'estimated_duration_minutes' => $this->lesson->estimated_duration_minutes,
            ],
            'assigned_blocks_count' => $this->whenCounted('sessionLessonBlocks'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
