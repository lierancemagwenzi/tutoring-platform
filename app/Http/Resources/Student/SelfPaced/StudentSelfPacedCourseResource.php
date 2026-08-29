<?php

namespace App\Http\Resources\Student\SelfPaced;

use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The course-player "bootstrap" payload: the full curriculum tree plus this
 * student's progress against it, in one response. Activities/assessments
 * are summarized here (id, type, title, required, completion) — their full
 * content is fetched lazily, one at a time, via their own show() endpoints.
 */
class StudentSelfPacedCourseResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $progress
     * @param  array<int, string>  $moduleStates
     * @param  Collection<int, int>  $completedActivityIds
     * @param  Collection<int, int>  $passedAssessmentIds
     */
    public function __construct(
        SelfPacedCourse $course,
        private readonly Enrollment $enrollment,
        private readonly array $progress,
        private readonly array $moduleStates,
        private readonly Collection $completedActivityIds,
        private readonly Collection $passedAssessmentIds,
    ) {
        parent::__construct($course);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'thumbnail_path' => $this->thumbnail_path,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'learning_objectives' => $this->learning_objectives,
            'tutor' => $this->whenLoaded('tutorProfile', fn () => [
                'id' => $this->tutorProfile->id,
                'display_name' => $this->tutorProfile->display_name,
                'profile_photo' => $this->tutorProfile->profile_photo,
            ]),
            'enrollment' => [
                'id' => $this->enrollment->id,
                'status' => $this->enrollment->status->value,
                'enrolled_at' => $this->enrollment->enrolled_at?->toIso8601String(),
                'completed_at' => $this->enrollment->completed_at?->toIso8601String(),
            ],
            'progress' => $this->progress,
            'modules' => $this->modules->map(fn ($module) => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'position' => $module->position,
                'state' => $this->moduleStates[$module->id] ?? 'locked',
                'activities' => $module->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'type' => $activity->type->value,
                    'title' => $activity->title,
                    'required' => $activity->required,
                    'position' => $activity->position,
                    'completed' => $this->completedActivityIds->contains($activity->id),
                ])->values(),
                'assessments' => $module->assessments->map(fn ($assessment) => [
                    'id' => $assessment->id,
                    'assessment_type' => $assessment->assessment_type->value,
                    'provider' => $assessment->provider?->value,
                    'title' => $assessment->title,
                    'required' => $assessment->required,
                    'position' => $assessment->position,
                    'passing_score' => $assessment->passing_score,
                    'attempts_mode' => $assessment->attempts_mode->value,
                    'max_attempts' => $assessment->max_attempts,
                    'passed' => $this->passedAssessmentIds->contains($assessment->id),
                ])->values(),
            ])->values(),
        ];
    }
}
