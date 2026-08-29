<?php

namespace App\Services\LearningHub;

use App\Enums\AttemptStatus;
use App\Enums\BookingStatus;
use App\Enums\LessonBlockType;
use App\Enums\SubmissionStatus;
use App\Models\LessonBlock;
use App\Models\SessionLessonBlock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Learning / Performance / Feedback domains of the Learning Hub.
 *
 * Every section here is a filter/sort/limit over the *same* underlying
 * dataset — the student's submission/attempt-eligible Session Lesson
 * Blocks, each paired with that student's latest submission or attempt —
 * computed once per request via items(), never re-queried per widget.
 * Scoring, availability, and passing rules are never recomputed: they come
 * straight from SessionLessonBlock::isAvailable() and the Submission/
 * Attempt records the Submission and Attempt Engines already produced.
 */
class LearningHubService
{
    /**
     * Statuses (across both SubmissionStatus and AttemptStatus, plus null
     * for "not started yet") the student can still act on directly.
     */
    private const CONTINUE_STATUSES = [
        null,
        SubmissionStatus::Draft->value,
        AttemptStatus::Started->value,
        AttemptStatus::InProgress->value,
    ];

    /**
     * Every status that counts as outstanding work, including states the
     * student can't act on yet (submitted/under review) but hasn't been
     * resolved either.
     */
    private const PENDING_STATUSES = [
        null,
        SubmissionStatus::Draft->value,
        AttemptStatus::Started->value,
        AttemptStatus::InProgress->value,
        SubmissionStatus::Submitted->value,
        SubmissionStatus::UnderReview->value,
        SubmissionStatus::Returned->value,
    ];

    /**
     * Statuses that represent a resolved outcome worth showing as a result.
     */
    private const RESULT_STATUSES = [
        SubmissionStatus::Graded->value,
        AttemptStatus::Completed->value,
        SubmissionStatus::Returned->value,
    ];

    /**
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $itemsCache = null;

    public function continueLearning(User $student, int $limit = 6): array
    {
        return $this->items($student)
            ->filter(fn (array $item) => $item['is_available'] && in_array($item['status'], self::CONTINUE_STATUSES, true))
            ->take($limit)
            ->values()
            ->all();
    }

    public function pendingActivities(User $student, int $limit = 10): array
    {
        return $this->items($student)
            ->filter(fn (array $item) => in_array($item['status'], self::PENDING_STATUSES, true))
            ->sortBy(fn (array $item) => $item['due_date'] ?? '9999-12-31')
            ->take($limit)
            ->values()
            ->all();
    }

    public function recentResults(User $student, int $limit = 10): array
    {
        // A Returned submission is always shown — it's the tutor asking for
        // a revision, not a grade release, so it isn't gated by publish_at
        // the way Graded results are.
        return $this->items($student)
            ->filter(fn (array $item) => in_array($item['status'], self::RESULT_STATUSES, true)
                && $item['resolved_at']
                && ($item['status'] === SubmissionStatus::Returned->value || $item['result_visible']))
            ->sortByDesc('resolved_at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array{average_percentage: float|null, activities_completed: int, by_type: array<string, array{average_percentage: float|null, count: int}>}
     */
    public function performanceSnapshot(User $student): array
    {
        $resolved = $this->items($student)
            ->filter(fn (array $item) => in_array($item['status'], ['graded', 'completed'], true) && $item['percentage'] !== null);

        $byType = $resolved->groupBy('block_type')->map(fn (Collection $group) => [
            'average_percentage' => round($group->avg('percentage'), 2),
            'count' => $group->count(),
        ]);

        return [
            'average_percentage' => $resolved->isNotEmpty() ? round($resolved->avg('percentage'), 2) : null,
            'activities_completed' => $resolved->count(),
            'by_type' => $byType->all(),
        ];
    }

    public function latestFeedback(User $student, int $limit = 5): array
    {
        return $this->items($student)
            ->filter(fn (array $item) => $item['feedback'] !== null)
            ->sortByDesc(fn (array $item) => $item['feedback']['date'])
            ->take($limit)
            ->values()
            ->map(fn (array $item) => [
                'title' => $item['title'],
                'block_type' => $item['block_type'],
                'url' => $item['url'],
                ...$item['feedback'],
            ])
            ->all();
    }

    public function notifications(User $student, int $limit = 8): array
    {
        $cutoff = Carbon::now()->subDays(14);
        $notifications = collect();

        foreach ($this->items($student) as $item) {
            if ($item['status'] === 'graded' && $item['resolved_at'] && $item['resolved_at']->greaterThanOrEqualTo($cutoff)) {
                $notifications->push([
                    'type' => 'graded',
                    'message' => "Your \"{$item['title']}\" was graded.",
                    'url' => $item['url'],
                    'date' => $item['resolved_at'],
                ]);
            }

            if ($item['status'] === 'returned' && $item['resolved_at'] && $item['resolved_at']->greaterThanOrEqualTo($cutoff)) {
                $notifications->push([
                    'type' => 'returned',
                    'message' => "\"{$item['title']}\" was returned for revision.",
                    'url' => $item['url'],
                    'date' => $item['resolved_at'],
                ]);
            }

            if (
                $item['status'] === null
                && $item['is_available']
                && $item['available_since']
                && $item['available_since']->greaterThanOrEqualTo($cutoff)
            ) {
                $notifications->push([
                    'type' => 'released',
                    'message' => "New activity available: \"{$item['title']}\".",
                    'url' => $item['url'],
                    'date' => $item['available_since'],
                ]);
            }
        }

        return $notifications->sortByDesc('date')->take($limit)->values()->all();
    }

    /**
     * @return array{sessions_today: int, pending_activities: int, awaiting_revision: int, average_percentage: float|null}
     */
    public function homeStats(User $student, int $todaysSessionCount): array
    {
        $items = $this->items($student);
        $snapshot = $this->performanceSnapshot($student);

        return [
            'sessions_today' => $todaysSessionCount,
            'pending_activities' => $items->filter(fn (array $item) => in_array($item['status'], self::PENDING_STATUSES, true))->count(),
            'awaiting_revision' => $items->filter(fn (array $item) => $item['status'] === 'returned')->count(),
            'average_percentage' => $snapshot['average_percentage'],
        ];
    }

    /**
     * The shared dataset every section above slices differently. Computed
     * once per request and memoized — nothing here re-derives availability,
     * attempt limits, or scoring; it only reads what SessionLessonBlock,
     * Submission and Attempt already computed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(User $student): Collection
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        // A booking can now have several sessions (a multi-session package)
        // and a session can belong to several bookings (a group class), so
        // this map is sourced from the pivot table rather than a column —
        // same "one booking id per teaching_session_id" output shape as
        // before, since a student's own booking maps 1:1 to any session it
        // includes.
        $bookingIdsByTeachingSession = DB::table('booking_teaching_session')
            ->join('bookings', 'bookings.id', '=', 'booking_teaching_session.booking_id')
            ->where('bookings.student_id', $student->id)
            ->where('bookings.status', BookingStatus::Confirmed->value)
            ->pluck('bookings.id', 'booking_teaching_session.teaching_session_id');

        if ($bookingIdsByTeachingSession->isEmpty()) {
            return $this->itemsCache = collect();
        }

        $sessionLessonBlocks = SessionLessonBlock::whereHas(
            'sessionLesson',
            fn ($query) => $query->whereIn('teaching_session_id', $bookingIdsByTeachingSession->keys()),
        )
            ->with([
                'lessonBlock',
                'sessionLesson.lesson',
                'sessionLesson.teachingSession',
                'submissions' => fn ($query) => $query->where('student_id', $student->id)->orderByDesc('attempt_number'),
                'attempts' => fn ($query) => $query->where('student_id', $student->id)->orderByDesc('attempt_number'),
            ])
            ->get()
            ->filter(fn (SessionLessonBlock $block) => $block->lessonBlock->supportsSubmissions() || $block->lessonBlock->attemptProvider() !== null);

        return $this->itemsCache = $sessionLessonBlocks
            ->map(fn (SessionLessonBlock $block) => $this->describe($block, $bookingIdsByTeachingSession, $student->id))
            ->values();
    }

    /**
     * @param  Collection<int, int>  $bookingIdsByTeachingSession
     * @return array<string, mixed>
     */
    private function describe(SessionLessonBlock $sessionLessonBlock, Collection $bookingIdsByTeachingSession, int $studentId): array
    {
        $block = $sessionLessonBlock->lessonBlock;
        $isSubmission = $block->supportsSubmissions();
        $latest = $isSubmission ? $sessionLessonBlock->submissions->first() : $sessionLessonBlock->attempts->first();
        $bookingId = $bookingIdsByTeachingSession->get($sessionLessonBlock->sessionLesson->teaching_session_id);

        $status = $latest?->status?->value;
        $maxScore = $isSubmission ? $block->learningActivity()?->max_score : $latest?->max_score;
        $score = $isSubmission ? $latest?->score : $latest?->raw_score;
        $percentage = $isSubmission
            ? ($score !== null && $maxScore > 0 ? round((float) $score / (float) $maxScore * 100, 2) : null)
            : $latest?->percentage;

        // A submission's grade stays hidden from the student until the
        // tutor publishes it — the hub must respect that same gate rather
        // than leaking it early via a different surface.
        $isVisibleResult = $isSubmission ? ($latest?->published_at !== null) : true;

        $feedbackHtml = $isSubmission ? data_get($latest?->feedback_text, 'html') : null;
        $feedback = ($feedbackHtml && $isVisibleResult) ? [
            'tutor' => $latest->reviewer ? trim("{$latest->reviewer->first_name} {$latest->reviewer->last_name}") : null,
            'feedback' => $feedbackHtml,
            'date' => $latest->reviewed_at,
        ] : null;

        return [
            'session_lesson_block_id' => $sessionLessonBlock->id,
            'lesson_block_id' => $block->id,
            'booking_id' => $bookingId,
            'kind' => $isSubmission ? 'submission' : 'attempt',
            'block_type' => $block->block_type->value,
            'title' => $this->titleFor($block, $isSubmission),
            'lesson_title' => $sessionLessonBlock->sessionLesson->lesson->title,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'score' => $isVisibleResult ? $score : null,
            'max_score' => $isVisibleResult ? $maxScore : null,
            'percentage' => $isVisibleResult ? $percentage : null,
            'passed' => $isVisibleResult ? $latest?->passed : null,
            'result_visible' => $isVisibleResult,
            'due_date' => $sessionLessonBlock->available_until?->toIso8601String() ?? $sessionLessonBlock->closes_at?->toIso8601String(),
            'attempts_remaining' => $this->attemptsRemaining($sessionLessonBlock, $isSubmission, $studentId),
            'resolved_at' => $isSubmission ? $latest?->reviewed_at : $latest?->completed_at,
            'available_since' => $sessionLessonBlock->created_at,
            'is_available' => $sessionLessonBlock->isAvailable(),
            'feedback' => $feedback,
            'url' => $bookingId ? "/student/bookings/{$bookingId}/lesson-blocks/{$block->id}" : null,
        ];
    }

    private function titleFor(LessonBlock $block, bool $isSubmission): ?string
    {
        if ($block->title) {
            return $block->title;
        }

        if ($isSubmission) {
            return $block->learningActivity()?->title;
        }

        if ($block->block_type === LessonBlockType::Quiz) {
            return $block->quiz()?->title;
        }

        return ucfirst(str_replace('_', ' ', $block->block_type->value));
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            null => 'Not Started',
            SubmissionStatus::Draft->value => 'Draft',
            AttemptStatus::Started->value, AttemptStatus::InProgress->value => 'In Progress',
            SubmissionStatus::Submitted->value => 'Submitted',
            SubmissionStatus::UnderReview->value => 'Under Review',
            SubmissionStatus::Returned->value => 'Needs Revision',
            SubmissionStatus::Graded->value => 'Graded',
            AttemptStatus::Completed->value => 'Completed',
            AttemptStatus::Abandoned->value => 'Abandoned',
            AttemptStatus::TimedOut->value => 'Timed Out',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function attemptsRemaining(SessionLessonBlock $sessionLessonBlock, bool $isSubmission, int $studentId): ?int
    {
        if ($isSubmission || $sessionLessonBlock->attempts_mode?->value !== 'limited' || ! $sessionLessonBlock->max_attempts) {
            return null;
        }

        // Reuses the exact same count the Attempt Engine itself enforces —
        // never a separately derived number.
        $used = $sessionLessonBlock->interactiveAttemptsUsedBy($studentId);

        return max(0, $sessionLessonBlock->max_attempts - $used);
    }
}
