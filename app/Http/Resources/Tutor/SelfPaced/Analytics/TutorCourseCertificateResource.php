<?php

namespace App\Http\Resources\Tutor\SelfPaced\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorCourseCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'student_name' => $this->student_name,
            'course_title' => $this->course_title,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'completed_at' => $this->whenLoaded('enrollment', fn () => $this->enrollment->completed_at?->toIso8601String()),
        ];
    }
}
