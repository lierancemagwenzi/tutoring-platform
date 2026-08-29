<?php

namespace App\Http\Resources\Student\SelfPaced;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'self_paced_course_id' => $this->whenLoaded('enrollment', fn () => $this->enrollment->self_paced_course_id),
            'certificate_number' => $this->certificate_number,
            'student_name' => $this->student_name,
            'course_title' => $this->course_title,
            'tutor_name' => $this->tutor_name,
            'issued_at' => $this->issued_at?->toIso8601String(),
        ];
    }
}
