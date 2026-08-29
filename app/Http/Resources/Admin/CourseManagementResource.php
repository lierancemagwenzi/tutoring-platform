<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'tutor' => $this->tutorProfile->display_name,
            'subject' => $this->subject?->name,
            'modules_count' => $this->modules_count,
            'enrollments_count' => $this->enrollments_count,
            'completed_enrollments_count' => $this->completed_enrollments_count,
            'completion_rate' => $this->enrollments_count > 0
                ? round($this->completed_enrollments_count / $this->enrollments_count * 100, 2)
                : 0.0,
            'certificates_issued' => $this->certificates_count,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
