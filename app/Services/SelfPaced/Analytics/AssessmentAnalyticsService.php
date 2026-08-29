<?php

namespace App\Services\SelfPaced\Analytics;

use App\Models\Enrollment;
use App\Models\SelfPacedAssessmentAttempt;

/**
 * Every assessment attempt a single student has made across a course,
 * grouped by assessment with best/latest score annotated — bounded to one
 * student's attempts, so a direct query is appropriate (no bulk/N+1
 * concerns the way a whole-course list would have).
 */
class AssessmentAnalyticsService
{
    /**
     * @return list<array{assessment_id: int, assessment_title: string, assessment_type: string, provider: ?string, passing_score: ?float, best_score: ?float, latest_score: ?float, passed: bool, attempts: list<array<string, mixed>>}>
     */
    public function attemptsFor(Enrollment $enrollment): array
    {
        $enrollment->loadMissing('course.modules.assessments');

        $assessments = $enrollment->course->modules->flatMap(fn ($module) => $module->assessments)->keyBy('id');

        $attempts = SelfPacedAssessmentAttempt::query()
            ->where('student_id', $enrollment->student_id)
            ->whereIn('self_paced_assessment_id', $assessments->keys())
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('self_paced_assessment_id');

        return $assessments->map(function ($assessment) use ($attempts) {
            $assessmentAttempts = $attempts->get($assessment->id, collect());
            $completed = $assessmentAttempts->whereNotNull('completed_at');
            $latest = $completed->sortByDesc('attempt_number')->first();

            return [
                'assessment_id' => $assessment->id,
                'assessment_title' => $assessment->title,
                'assessment_type' => $assessment->assessment_type->value,
                'provider' => $assessment->provider?->value,
                'passing_score' => $assessment->passing_score !== null ? (float) $assessment->passing_score : null,
                'best_score' => $completed->max('percentage'),
                'latest_score' => $latest?->percentage,
                'passed' => $completed->contains('passed', true),
                'attempts' => $assessmentAttempts->map(fn ($attempt) => [
                    'attempt_number' => $attempt->attempt_number,
                    'status' => $attempt->status->value,
                    'started_at' => $attempt->started_at?->toIso8601String(),
                    'completed_at' => $attempt->completed_at?->toIso8601String(),
                    'raw_score' => $attempt->raw_score,
                    'max_score' => $attempt->max_score,
                    'percentage' => $attempt->percentage,
                    'passed' => $attempt->passed,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
