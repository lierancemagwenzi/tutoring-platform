<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningActivityResource extends JsonResource
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
            'lesson_id' => $this->lesson_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'status' => $this->status->value,
            'submission_type' => $this->submission_type->value,
            'max_score' => $this->max_score,
            'settings' => $this->settings,
            'attachments' => ActivityAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
