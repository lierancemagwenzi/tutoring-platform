<?php

namespace App\Services\SelfPaced\Analytics;

use App\Models\ActivityProgress;
use App\Models\Enrollment;
use App\Models\ModuleProgress;
use App\Models\SelfPacedAssessmentAttempt;
use App\Services\LearnerProgress\CourseProgressService;
use App\Services\LearnerProgress\ModuleProgressService;
use Illuminate\Support\Collection;

/**
 * The tutor-facing detail view for a single student's learning journey —
 * everything here is bounded to one enrollment (a handful of modules and
 * attempts), so unlike CourseAnalyticsService/EnrollmentAnalyticsService it
 * reuses the existing single-enrollment services directly rather than
 * introducing bulk queries there's no need for.
 */
class StudentProgressService
{
    public function __construct(
        private readonly CourseProgressService $courseProgress,
        private readonly ModuleProgressService $moduleProgress,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(Enrollment $enrollment): array
    {
        $enrollment->loadMissing(['student', 'course.modules.activities', 'course.modules.assessments', 'certificate']);
        $modules = $enrollment->course->modules;

        $summary = $this->courseProgress->summarize($enrollment);
        $moduleStates = $this->moduleProgress->statesFor($enrollment, $modules);

        $moduleProgressByModuleId = ModuleProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->get()
            ->keyBy('self_paced_module_id');

        $activityProgressByActivityId = ActivityProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->get()
            ->keyBy('self_paced_activity_id');

        $assessmentIds = $modules->flatMap(fn ($module) => $module->assessments->pluck('id'));
        $attemptsByAssessmentId = SelfPacedAssessmentAttempt::query()
            ->where('student_id', $enrollment->student_id)
            ->whereIn('self_paced_assessment_id', $assessmentIds)
            ->get()
            ->groupBy('self_paced_assessment_id');

        return [
            'student' => [
                'id' => $enrollment->student->id,
                'first_name' => $enrollment->student->first_name,
                'last_name' => $enrollment->student->last_name,
                'email' => $enrollment->student->email,
            ],
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status->value,
                'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
                'completed_at' => $enrollment->completed_at?->toIso8601String(),
                'last_accessed_at' => $enrollment->last_accessed_at?->toIso8601String(),
            ],
            'progress' => $summary,
            'certificate' => $enrollment->certificate ? [
                'id' => $enrollment->certificate->id,
                'certificate_number' => $enrollment->certificate->certificate_number,
                'issued_at' => $enrollment->certificate->issued_at?->toIso8601String(),
            ] : null,
            'chapters' => $modules->map(function ($module) use ($moduleStates, $moduleProgressByModuleId, $activityProgressByActivityId, $attemptsByAssessmentId) {
                $moduleProgress = $moduleProgressByModuleId->get($module->id);
                $totalItems = $module->activities->count() + $module->assessments->count();
                $doneItems = $module->activities->filter(
                    fn ($activity) => $activityProgressByActivityId->get($activity->id)?->completed_at !== null,
                )->count() + $module->assessments->filter(
                    fn ($assessment) => $attemptsByAssessmentId->get($assessment->id, collect())->contains('passed', true),
                )->count();

                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'position' => $module->position,
                    'state' => $moduleStates[$module->id] ?? 'locked',
                    'completion_percentage' => $totalItems > 0 ? round($doneItems / $totalItems * 100, 2) : 0.0,
                    'completed_at' => $moduleProgress?->completed_at?->toIso8601String(),
                    'lesson_blocks' => [
                        ...$module->activities->map(function ($activity) use ($activityProgressByActivityId) {
                            $progress = $activityProgressByActivityId->get($activity->id);

                            return [
                                'kind' => 'activity',
                                'id' => $activity->id,
                                'type' => $activity->type->value,
                                'title' => $activity->title,
                                'required' => $activity->required,
                                'completed' => $progress?->completed_at !== null,
                                'completed_at' => $progress?->completed_at?->toIso8601String(),
                            ];
                        })->values(),
                        ...$module->assessments->map(function ($assessment) use ($attemptsByAssessmentId) {
                            $attempts = $attemptsByAssessmentId->get($assessment->id, collect());
                            $completedAttempts = $attempts->whereNotNull('completed_at');
                            $latest = $completedAttempts->sortByDesc('attempt_number')->first();

                            return [
                                'kind' => 'assessment',
                                'id' => $assessment->id,
                                'type' => 'assessment',
                                'provider' => $assessment->provider?->value,
                                'title' => $assessment->title,
                                'required' => $assessment->required,
                                'completed' => $completedAttempts->isNotEmpty(),
                                'completed_at' => $latest?->completed_at?->toIso8601String(),
                                'attempts' => $completedAttempts->count(),
                                'best_score' => $completedAttempts->max('percentage'),
                                'latest_score' => $latest?->percentage,
                                'passed' => $completedAttempts->contains('passed', true),
                            ];
                        })->values(),
                    ],
                ];
            })->values(),
            'timeline' => $this->timeline(
                $enrollment,
                $moduleProgressByModuleId,
                $attemptsByAssessmentId,
                $modules->pluck('title', 'id'),
                $modules->flatMap(fn ($module) => $module->assessments->pluck('title', 'id')),
            ),
        ];
    }

    /**
     * The student's learning history, chronologically — enrollment, each
     * chapter completed, each assessment attempt completed, and the
     * certificate earned, merged and sorted by timestamp.
     *
     * @param  Collection<int, ModuleProgress>  $moduleProgressByModuleId
     * @param  Collection<int, Collection<int, SelfPacedAssessmentAttempt>>  $attemptsByAssessmentId
     * @param  Collection<int, string>  $moduleTitlesById
     * @param  Collection<int, string>  $assessmentTitlesById
     * @return list<array{type: string, label: string, at: string}>
     */
    private function timeline(
        Enrollment $enrollment,
        $moduleProgressByModuleId,
        $attemptsByAssessmentId,
        $moduleTitlesById,
        $assessmentTitlesById,
    ): array {
        // completed_at columns store whole-second precision, so two events
        // in the same request (e.g. a module completing as the direct
        // result of the assessment that just passed it) frequently tie down
        // to the second — sub-second causality isn't recoverable from that,
        // so this only guarantees the two orderings that are ALWAYS true
        // structurally, regardless of timing: nothing precedes enrollment,
        // and the certificate is only ever issued once every module is
        // already confirmed complete, so it always sorts last.
        $priority = ['enrolled' => 0, 'certificate_earned' => 2];

        $events = collect();

        if ($enrollment->enrolled_at) {
            $events->push(['type' => 'enrolled', 'label' => 'Enrolled', 'at' => $enrollment->enrolled_at]);
        }

        foreach ($moduleProgressByModuleId as $moduleId => $moduleProgress) {
            if ($moduleProgress->completed_at) {
                $title = $moduleTitlesById[$moduleId] ?? 'a chapter';
                $events->push([
                    'type' => 'chapter_completed',
                    'label' => "Completed {$title}",
                    'at' => $moduleProgress->completed_at,
                ]);
            }
        }

        foreach ($attemptsByAssessmentId as $assessmentId => $attempts) {
            $title = $assessmentTitlesById[$assessmentId] ?? 'Assessment';

            foreach ($attempts->whereNotNull('completed_at') as $attempt) {
                $events->push([
                    'type' => $attempt->passed ? 'assessment_passed' : 'assessment_failed',
                    'label' => ($attempt->passed ? 'Passed ' : 'Attempted ').$title,
                    'at' => $attempt->completed_at,
                ]);
            }
        }

        if ($enrollment->certificate) {
            $events->push(['type' => 'certificate_earned', 'label' => 'Earned Certificate', 'at' => $enrollment->certificate->issued_at]);
        }

        return $events
            ->sortBy(fn ($event) => $event['at']->format('Y-m-d H:i:s').'-'.($priority[$event['type']] ?? 1))
            ->values()
            ->map(fn ($event) => [
                'type' => $event['type'],
                'label' => $event['label'],
                'at' => $event['at']->toIso8601String(),
            ])->all();
    }
}
