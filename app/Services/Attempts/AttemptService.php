<?php

namespace App\Services\Attempts;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\LessonBlock;
use App\Models\SessionLessonBlock;
use App\Models\User;

class AttemptService
{
    /**
     * Launch an activity: resumes the student's still-open attempt (started
     * or in progress) if one exists, otherwise starts a new one. Only a
     * finished attempt consumes an attempt slot — see
     * SessionLessonBlock::interactiveAttemptsUsedBy().
     */
    public function start(SessionLessonBlock $sessionLessonBlock, LessonBlock $block, User $student): Attempt
    {
        $open = $sessionLessonBlock->attempts()
            ->where('student_id', $student->id)
            ->whereIn('status', [AttemptStatus::Started->value, AttemptStatus::InProgress->value])
            ->first();

        if ($open) {
            return $open;
        }

        return $sessionLessonBlock->attempts()->create([
            'student_id' => $student->id,
            'provider' => $block->attemptProvider(),
            'attempt_number' => $sessionLessonBlock->interactiveAttemptsUsedBy($student->id) + 1,
            'status' => AttemptStatus::Started,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark an attempt as actively under way, once the provider's player has
     * actually initialized (e.g. H5P's `initialized` event).
     */
    public function markInProgress(Attempt $attempt): Attempt
    {
        if ($attempt->status === AttemptStatus::Started) {
            $attempt->update(['status' => AttemptStatus::InProgress]);
        }

        return $attempt;
    }

    /**
     * Complete an attempt: hands the provider's raw result to the matching
     * AttemptResultHandler, then derives pass/fail from the Session Lesson
     * Block's own Passing Score — never a separately stored rule.
     *
     * @param  array<string, mixed>  $rawResult
     */
    public function complete(Attempt $attempt, array $rawResult): Attempt
    {
        $parsed = AttemptResultHandlerFactory::make($attempt->provider)->parseResult($attempt, $rawResult);

        $passingScore = $attempt->sessionLessonBlock->passing_score;
        $passed = $passingScore !== null && $parsed['raw_score'] !== null
            ? $parsed['raw_score'] >= (float) $passingScore
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

        return $attempt;
    }
}
