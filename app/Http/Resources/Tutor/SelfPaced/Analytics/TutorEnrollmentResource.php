<?php

namespace App\Http\Resources\Tutor\SelfPaced\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the tutor-facing "Enrolled Students" (and, filtered,
 * "Completed Students") list. The `analytics` array is set by
 * EnrollmentAnalyticsService::listForCourse() via setAttribute() — computed
 * in bulk for the whole page, never per-row here.
 */
class TutorEnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => [
                'id' => $this->student->id,
                'name' => trim("{$this->student->first_name} {$this->student->last_name}"),
                'email' => $this->student->email,
            ],
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'progress_percentage' => $this->analytics['progress_percentage'],
            'current_chapter_id' => $this->analytics['current_module_id'],
            'current_chapter_title' => $this->analytics['current_module_title'],
            'course_status' => $this->analytics['course_status'],
            'average_assessment_score' => $this->analytics['average_assessment_score'],
            'certificate_issued' => $this->analytics['has_certificate'],
            'certificate_number' => $this->analytics['certificate_number'],
            'days_since_last_activity' => $this->analytics['days_since_last_activity'],
            'is_inactive' => $this->analytics['is_inactive'],
        ];
    }
}
