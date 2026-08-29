<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'student' => $this->student_name,
            'course_title' => $this->course_title,
            'tutor' => $this->tutor_name,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'status' => 'issued',
        ];
    }
}
