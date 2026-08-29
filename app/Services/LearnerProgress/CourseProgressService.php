<?php

namespace App\Services\LearnerProgress;

use App\Models\ActivityProgress;
use App\Models\Enrollment;
use App\Models\SelfPacedAssessmentAttempt;

/**
 * Read-side aggregator for a student's course-level progress — computed
 * entirely from ModuleProgress/ActivityProgress/SelfPacedAssessmentAttempt
 * rows, nothing stored redundantly on Enrollment (same philosophy as
 * BookingProgressService computing booking progress from Sessions).
 */
class CourseProgressService
{
    public function __construct(
        private readonly ModuleProgressService $moduleProgress,
    ) {}

    /**
     * @return array{overall_percentage: float, completed_chapters: int, remaining_chapters: int, total_chapters: int, assessments_passed: int, current_chapter_id: ?int, current_chapter_title: ?string, learning_time_seconds: int, average_score: ?float}
     */
    public function summarize(Enrollment $enrollment): array
    {
        $course = $enrollment->course()->with(['modules.activities', 'modules.assessments'])->first();
        $modules = $course->modules;

        $states = $this->moduleProgress->statesFor($enrollment, $modules);

        $totalCount = $modules->count();
        $completedCount = collect($states)->filter(fn ($state) => $state === 'completed')->count();
        $currentModule = $modules->first(fn ($module) => ($states[$module->id] ?? null) === 'current');

        $assessmentIds = $modules->flatMap(fn ($module) => $module->assessments->pluck('id'));
        $activityIds = $modules->flatMap(fn ($module) => $module->activities->pluck('id'));

        $assessmentsPassed = SelfPacedAssessmentAttempt::query()
            ->where('student_id', $enrollment->student_id)
            ->whereIn('self_paced_assessment_id', $assessmentIds)
            ->where('passed', true)
            ->distinct('self_paced_assessment_id')
            ->count('self_paced_assessment_id');

        $activityTimeSeconds = (int) ActivityProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('self_paced_activity_id', $activityIds)
            ->sum('time_spent_seconds');

        $assessmentTimeSeconds = (int) SelfPacedAssessmentAttempt::query()
            ->where('student_id', $enrollment->student_id)
            ->whereIn('self_paced_assessment_id', $assessmentIds)
            ->sum('time_taken_seconds');

        $averageScore = SelfPacedAssessmentAttempt::query()
            ->where('student_id', $enrollment->student_id)
            ->whereIn('self_paced_assessment_id', $assessmentIds)
            ->where('passed', true)
            ->avg('percentage');

        return [
            'overall_percentage' => $totalCount > 0 ? round($completedCount / $totalCount * 100, 2) : 0.0,
            'completed_chapters' => $completedCount,
            'remaining_chapters' => $totalCount - $completedCount,
            'total_chapters' => $totalCount,
            'assessments_passed' => $assessmentsPassed,
            'current_chapter_id' => $currentModule?->id,
            'current_chapter_title' => $currentModule?->title,
            'learning_time_seconds' => $activityTimeSeconds + $assessmentTimeSeconds,
            'average_score' => $averageScore !== null ? round((float) $averageScore, 2) : null,
        ];
    }
}
