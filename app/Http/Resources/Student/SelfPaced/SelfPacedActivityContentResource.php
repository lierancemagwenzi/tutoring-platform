<?php

namespace App\Http\Resources\Student\SelfPaced;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A single activity's full content for the student player — resolved
 * attachment URLs (not just raw storage paths, unlike the authoring-side
 * SelfPacedActivityResource) since this is meant for direct consumption
 * (video src, download links).
 */
class SelfPacedActivityContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'self_paced_module_id' => $this->self_paced_module_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'required' => $this->required,
            'content' => $this->content,
            'settings' => $this->settings,
            'completed' => (bool) ($this->completed ?? false),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'media_type' => $attachment->media_type->value,
                'title' => $attachment->title,
                'url' => $attachment->file_path ? Storage::disk('public')->url($attachment->file_path) : $attachment->external_url,
                'original_name' => $attachment->original_name,
                'size' => $attachment->size,
                'position' => $attachment->position,
            ])->values()),
        ];
    }
}
