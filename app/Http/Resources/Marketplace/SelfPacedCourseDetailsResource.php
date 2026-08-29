<?php

namespace App\Http\Resources\Marketplace;

use App\Enums\SelfPacedCourseStatus;
use App\Http\Resources\TutorQualificationResource;
use App\Services\Marketplace\SelfPacedCoursePricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedCourseDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $modules = $this->whenLoaded('modules');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'promo_description' => $this->promo_description,
            'thumbnail_path' => $this->thumbnail_path,
            'promo_video_path' => $this->promo_video_path,
            'is_enrolled' => (bool) ($this->is_enrolled ?? false),
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? ['id' => $this->subject->id, 'name' => $this->subject->name] : null),
            'grade' => $this->whenLoaded('grade', fn () => $this->grade ? ['id' => $this->grade->id, 'name' => $this->grade->name] : null),
            'difficulty' => $this->difficulty?->value,
            'language' => $this->language,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'learning_objectives' => $this->learning_objectives ?? [],
            'prerequisites' => $this->prerequisites ?? [],
            'target_audience' => $this->target_audience ?? [],
            'pricing' => app(SelfPacedCoursePricingService::class)->resolve($this->resource),
            'tutor' => $this->whenLoaded('tutorProfile', fn () => $this->tutorSection()),
            'modules' => $modules === null ? [] : $modules->map(fn ($module) => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'activities_count' => $module->activities->count(),
                'assessments_count' => $module->assessments->count(),
                'activities' => $module->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'type' => $activity->type->value,
                    'required' => $activity->required,
                ])->values(),
                'assessments' => $module->assessments->map(fn ($assessment) => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'assessment_type' => $assessment->assessment_type->value,
                    'required' => $assessment->required,
                ])->values(),
            ])->values(),
            'modules_count' => $modules?->count() ?? 0,
            'activities_count' => $modules === null ? 0 : $modules->sum(fn ($module) => $module->activities->count()),
            'assessments_count' => $modules === null ? 0 : $modules->sum(fn ($module) => $module->assessments->count()),
        ];
    }

    /**
     * The tutor card shown on a course's details page — bio, qualifications
     * and years of experience are generic and safe to reuse verbatim, but
     * "subjects taught" is derived from this tutor's approved TutorSubject
     * assignments rather than Tutor-Led Learning's published Services,
     * since a self-paced-only tutor may have none of the latter.
     *
     * @return array<string, mixed>
     */
    private function tutorSection(): array
    {
        $tutor = $this->tutorProfile;

        $subjects = $tutor->relationLoaded('tutorSubjects')
            ? $tutor->tutorSubjects
                ->where('status', 'approved')
                ->pluck('subject')
                ->filter()
                ->unique('id')
                ->map(fn ($subject) => ['id' => $subject->id, 'name' => $subject->name])
                ->values()
            : [];

        return [
            'id' => $tutor->id,
            'display_name' => $tutor->display_name,
            'profile_photo' => $tutor->profile_photo,
            'bio' => $tutor->bio,
            'years_experience' => $tutor->years_experience,
            'qualifications' => TutorQualificationResource::collection(
                $tutor->relationLoaded('qualifications') ? $tutor->qualifications : collect(),
            ),
            'subjects' => $subjects,
            'other_published_courses_count' => $tutor->selfPacedCourses()
                ->where('status', SelfPacedCourseStatus::Published)
                ->where('id', '!=', $this->id)
                ->count(),
        ];
    }
}
