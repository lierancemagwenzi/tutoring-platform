<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaItemResource extends JsonResource
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
            'lesson_block_id' => $this->lesson_block_id,
            'media_type' => $this->media_type->value,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->file_path ? Storage::disk('public')->url($this->file_path) : $this->external_url,
            'thumbnail_url' => $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'position' => $this->position,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
