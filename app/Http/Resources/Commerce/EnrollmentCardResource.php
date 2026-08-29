<?php

namespace App\Http\Resources\Commerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentCardResource extends JsonResource
{
    /**
     * Transform the resource into an array — the "My Courses" grid shape.
     * No pricing block: the course is already owned, so price is
     * irrelevant and shouldn't risk showing a since-changed price.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $course = $this->course;

        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'progress_percentage' => $this->progress['overall_percentage'] ?? null,
            'certificate' => $this->whenLoaded('certificate', fn () => $this->certificate ? [
                'id' => $this->certificate->id,
                'certificate_number' => $this->certificate->certificate_number,
                'issued_at' => $this->certificate->issued_at?->toIso8601String(),
            ] : null),
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'thumbnail_path' => $course->thumbnail_path,
                'difficulty' => $course->difficulty?->value,
                'subject' => $course->relationLoaded('subject') && $course->subject
                    ? ['id' => $course->subject->id, 'name' => $course->subject->name]
                    : null,
                'grade' => $course->relationLoaded('grade') && $course->grade
                    ? ['id' => $course->grade->id, 'name' => $course->grade->name]
                    : null,
                'tutor' => $course->relationLoaded('tutorProfile') && $course->tutorProfile ? [
                    'id' => $course->tutorProfile->id,
                    'display_name' => $course->tutorProfile->display_name,
                    'profile_photo' => $course->tutorProfile->profile_photo,
                ] : null,
            ],
        ];
    }
}
