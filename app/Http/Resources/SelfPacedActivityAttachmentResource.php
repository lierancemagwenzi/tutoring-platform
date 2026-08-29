<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedActivityAttachmentResource extends JsonResource
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
            'self_paced_activity_id' => $this->self_paced_activity_id,
            'media_type' => $this->media_type->value,
            'title' => $this->title,
            'file_path' => $this->file_path,
            'external_url' => $this->external_url,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'position' => $this->position,
        ];
    }
}
