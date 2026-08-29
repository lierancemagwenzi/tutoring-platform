<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedActivityResource extends JsonResource
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
            'self_paced_module_id' => $this->self_paced_module_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'required' => $this->required,
            'content' => $this->content,
            'settings' => $this->settings,
            'has_required_content' => $this->hasRequiredContent(),
            'attachments' => SelfPacedActivityAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
