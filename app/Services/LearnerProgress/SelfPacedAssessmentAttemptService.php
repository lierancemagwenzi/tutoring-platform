<?php

namespace App\Services\LearnerProgress;

use App\Enums\AttemptStatus;
use App\Enums\SelfPacedAttemptsMode;
use App\Models\Enrollment;
use App\Models\SelfPacedAssessment;
use App\Models\SelfPacedAssessmentAttempt;
use RuntimeException;

/**
 * The self-paced side's equivalent of AttemptService — same start/
 * markInProgress/complete lifecycle, scoped to SelfPacedAssessment/
 * SelfPacedAssessmentAttempt instead of LessonBlock/Attempt. Pass/fail is
 * derived from percentage (not raw score) against the assessment's
 * passing_score: unlike a single fixed-point-total Tutor-Led lesson block,
 * a self-paced assessment's max possible score varies with its underlying
 * question bank, so only a percentage-based pass mark is portable.
 */
class SelfPacedAssessmentAttemptService
{
    public function __construct(
        private readonly ModuleProgressService $moduleProgress,
        private readonly CourseCompletionService $courseCompletion,
    ) {}

    /**
     * Launch an assessment: resumes the student's still-open attempt if one
     * exists, otherwise starts a new one — enforcing attempts_mode/
     * max_attempts. Only a completed attempt consumes an attempt slot.
     */
    public function start(SelfPacedAssessment $assessment, Enrollment $enrollment): SelfPacedAssessmentAttempt
    {
        $this->moduleProgress->assertUnlocked($enrollment, $assessment->module);

        $open = SelfPacedAssessmentAttempt::query()
            ->where('self_paced_assessment_id', $assessment->id)
            ->where('student_id', $enrollment->student_id)
            ->whereIn('status', [AttemptStatus::Started->value, AttemptStatus::InProgress->value])
            ->first();

        if ($open) {
            return $open;
        }

        $completedCount = SelfPacedAssessmentAttempt::query()
            ->where('self_paced_assessment_id', $assessment->id)
            ->where('student_id', $enrollment->student_id)
            ->whereNotNull('completed_at')
            ->count();

        if ($assessment->attempts_mode === SelfPacedAttemptsMode::Limited && $completedCount >= ($assessment->max_attempts ?? 0)) {
            throw new RuntimeException('You have reached the maximum number of attempts for this assessment.');
        }

        $enrollment->update(['last_accessed_at' => now()]);

        return SelfPacedAssessmentAttempt::create([
            'self_paced_assessment_id' => $assessment->id,
            'student_id' => $enrollment->student_id,
            'provider' => $assessment->provider,
            'attempt_number' => $completedCount + 1,
            'status' => AttemptStatus::Started,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark an attempt as actively under way, once the provider's player has
     * actually initialized (e.g. H5P's `initialized` event).
     */
    public function markInProgress(SelfPacedAssessmentAttempt $attempt): SelfPacedAssessmentAttempt
    {
        if ($attempt->status === AttemptStatus::Started) {
            $attempt->update(['status' => AttemptStatus::InProgress]);
        }

        return $attempt;
    }

    /**
     * Complete an attempt: hands the provider's raw result to the matching
     * SelfPacedAttemptResultHandler, derives pass/fail, then re-evaluates
     * the owning module/course — this is how a passed assessment
     * automatically unlocks the next chapter / completes the course.
     *
     * @param  array<string, mixed>  $rawResult
     */
    public function complete(SelfPacedAssessmentAttempt $attempt, array $rawResult, Enrollment $enrollment): SelfPacedAssessmentAttempt
    {
        $parsed = SelfPacedAttemptResultHandlerFactory::make($attempt->provider)->parseResult($attempt, $rawResult);

        $passingScore = $attempt->assessment->passing_score;
        $passed = $passingScore !== null && $parsed['percentage'] !== null
            ? $parsed['percentage'] >= (float) $passingScore
            : null;

        $attempt->update([
            'status' => AttemptStatus::Completed,
            'completed_at' => now(),
            'time_taken_seconds' => (int) round($attempt->started_at->diffInSeconds(now(), absolute: true)),
            'raw_score' => $parsed['raw_score'],
            'max_score' => $parsed['max_score'],
            'percentage' => $parsed['percentage'],
            'passed' => $passed,
            'raw_provider_response' => $rawResult,
            'provider_metadata' => $parsed['provider_metadata'],
        ]);

        $enrollment->update(['last_accessed_at' => now()]);

        $this->moduleProgress->evaluate($enrollment, $attempt->assessment->module);
        $this->courseCompletion->evaluate($enrollment);

        return $attempt->fresh();
    }
}
