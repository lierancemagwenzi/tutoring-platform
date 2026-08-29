<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SubmissionAttachmentResource extends JsonResource
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
            'submission_id' => $this->submission_id,
            'category' => $this->category->value,
            'media_type' => $this->media_type->value,
            'title' => $this->title,
            'url' => $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'position' => $this->position,
            'created_at' => $this->created_at,
        ];
    }
}
