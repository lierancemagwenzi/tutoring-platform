<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'services_count' => $this->whenCounted('services'),
            'self_paced_courses_count' => $this->whenCounted('selfPacedCourses'),
        ];
    }
}
