<?php

namespace App\Services\Admin;

use App\Enums\EnrollmentStatus;
use App\Models\SelfPacedCourse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only admin visibility into self-paced courses — list-level stats
 * (enrollments, completion rate, certificates) via aggregate queries, and
 * a full content-tree inspection (modules -> activities/assessments) for a
 * single course. Never touches learner progress rows.
 */
class AdminCourseManagementService
{
    /**
     * @param  array{status?: string, search?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = SelfPacedCourse::query()
            ->with(['tutorProfile', 'subject'])
            ->withCount([
                'enrollments',
                'enrollments as completed_enrollments_count' => fn ($q) => $q->where('status', EnrollmentStatus::Completed),
                'modules',
            ])
            ->withCount('certificates');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(SelfPacedCourse $course): array
    {
        $course->loadMissing(['tutorProfile', 'subject', 'modules.activities', 'modules.assessments']);
        $course->loadCount([
            'enrollments',
            'enrollments as completed_enrollments_count' => fn ($q) => $q->where('status', EnrollmentStatus::Completed),
            'certificates',
        ]);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'status' => $course->status->value,
            'tutor' => $course->tutorProfile->display_name,
            'subject' => $course->subject?->name,
            'price' => $course->price,
            'currency' => $course->currency,
            'enrollments_count' => $course->enrollments_count,
            'completed_enrollments_count' => $course->completed_enrollments_count,
            'completion_rate' => $course->enrollments_count > 0
                ? round($course->completed_enrollments_count / $course->enrollments_count * 100, 2)
                : 0.0,
            'certificates_issued' => $course->certificates_count,
            'modules' => $course->modules->sortBy('position')->map(fn ($module) => [
                'id' => $module->id,
                'title' => $module->title,
                'position' => $module->position,
                'activities' => $module->activities->sortBy('position')->map(fn ($activity) => [
                    'id' => $activity->id,
                    'type' => $activity->type->value,
                    'title' => $activity->title,
                    'required' => $activity->required,
                ])->values(),
                'assessments' => $module->assessments->sortBy('position')->map(fn ($assessment) => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'assessment_type' => $assessment->assessment_type->value,
                    'provider' => $assessment->provider?->value,
                    'required' => $assessment->required,
                ])->values(),
            ])->values(),
        ];
    }
}
