<?php

namespace App\Services\TutorWorkspace;

use App\Enums\AttemptStatus;
use App\Enums\BookingStatus;
use App\Enums\SessionLessonBlockCompletionMode;
use App\Enums\SessionStatus;
use App\Enums\SubmissionStatus;
use App\Models\Attempt;
use App\Models\LessonBlock;
use App\Models\SessionLessonBlock;
use App\Models\Submission;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Students and Reviews domains of the Tutor Workspace.
 *
 * Every section here is a filter/sort/limit over the *same* underlying
 * dataset — the latest submission or attempt each of this tutor's students
 * has made against each of the tutor's Session Lesson Blocks — computed
 * once per request via items(), never re-queried per widget. Scoring and
 * status are never recomputed: they come straight from the Submission and
 * Attempt records the Submission and Attempt Engines already produced.
 */
class StudentWorkService
{
    private const REVIEW_STATUSES = [
        SubmissionStatus::Submitted->value,
        SubmissionStatus::UnderReview->value,
    ];

    private const RESULT_STATUSES = [
        SubmissionStatus::Graded->value,
        AttemptStatus::Completed->value,
    ];

    /**
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $itemsCache = null;

    /**
     * @return Collection<int, User>
     */
    public function activeStudents(TutorProfile $tutor): Collection
    {
        $studentIds = $tutor->bookings()->where('status', BookingStatus::Confirmed)->pluck('student_id')->unique();

        return User::whereIn('id', $studentIds)->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingReviews(TutorProfile $tutor, int $limit = 15): array
    {
        return $this->items($tutor)
            ->filter(fn (array $item) => $item['kind'] === 'submission' && in_array($item['status'], self::REVIEW_STATUSES, true))
            ->sortBy('submitted_at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivity(TutorProfile $tutor, int $limit = 15): array
    {
        return $this->items($tutor)
            ->filter(fn (array $item) => $item['activity_at'] !== null)
            ->sortByDesc('activity_at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array{average_percentage: float|null, activities_completed: int, by_type: array<string, array{average_percentage: float|null, count: int}>}
     */
    public function performanceSnapshot(TutorProfile $tutor): array
    {
        $resolved = $this->items($tutor)
            ->filter(fn (array $item) => in_array($item['status'], self::RESULT_STATUSES, true) && $item['percentage'] !== null);

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

    /**
     * Students with at least one reason to flag them for tutor attention —
     * grouped per student since the widget shows one card per student with
     * a chip per reason, each reason carrying its own direct link.
     *
     * @return list<array{student_id: int, student_name: string, reasons: list<array{label: string, url: ?string}>}>
     */
    public function studentsRequiringAttention(TutorProfile $tutor, int $limit = 10): array
    {
        $items = $this->items($tutor);
        $itemsByStudent = $items->groupBy('student_id');
        $students = $this->activeStudents($tutor)->keyBy('id');
        $missingHomework = $this->missingHomeworkByStudent($tutor);
        $missedSessions = $this->missedSessionsByStudent($tutor);

        $flagged = [];

        foreach ($students as $studentId => $student) {
            $studentItems = $itemsByStudent->get($studentId, collect());
            $reasons = [];

            $returned = $studentItems->first(fn (array $item) => $item['status'] === SubmissionStatus::Returned->value);
            if ($returned) {
                $reasons[] = ['label' => 'Returned assignment awaiting resubmission', 'url' => $returned['url']];
            }

            $unpublished = $studentItems->first(
                fn (array $item) => $item['kind'] === 'submission'
                    && $item['status'] === SubmissionStatus::Graded->value
                    && $item['published_at'] === null,
            );
            if ($unpublished) {
                $reasons[] = ['label' => 'Graded work awaiting your published feedback', 'url' => $unpublished['url']];
            }

            $recentResults = $studentItems
                ->filter(fn (array $item) => in_array($item['status'], self::RESULT_STATUSES, true) && $item['percentage'] !== null)
                ->sortByDesc('resolved_at')
                ->take(5);
            if ($recentResults->isNotEmpty() && $recentResults->avg('percentage') < 60) {
                $reasons[] = ['label' => 'Low recent scores', 'url' => $recentResults->first()['url']];
            }

            $lastActivity = $studentItems->max('activity_at');
            if ($lastActivity === null || Carbon::parse($lastActivity)->lt(Carbon::now()->subDays(14))) {
                $reasons[] = ['label' => 'No recent activity', 'url' => null];
            }

            foreach ($missingHomework->get($studentId, []) as $entry) {
                $reasons[] = ['label' => "Missing: {$entry['title']}", 'url' => $entry['url']];
            }

            if ($missedSessions->has($studentId)) {
                $reasons[] = ['label' => 'Possibly missed a scheduled session', 'url' => null];
            }

            if ($reasons !== []) {
                $flagged[] = [
                    'student_id' => $studentId,
                    'student_name' => trim("{$student->first_name} {$student->last_name}"),
                    'reasons' => $reasons,
                ];
            }
        }

        return array_slice($flagged, 0, $limit);
    }

    /**
     * The shared dataset every section above slices differently: the latest
     * submission or attempt each student has made against each of this
     * tutor's Session Lesson Blocks. Computed once per request and
     * memoized.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(TutorProfile $tutor): Collection
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        $sessionLessonBlocks = SessionLessonBlock::whereHas(
            'sessionLesson.teachingSession',
            fn ($query) => $query->where('tutor_profile_id', $tutor->id),
        )
            ->with([
                'lessonBlock',
                'sessionLesson.lesson',
                'sessionLesson.teachingSession',
                'submissions.student',
                'attempts.student',
            ])
            ->get()
            ->filter(fn (SessionLessonBlock $block) => $block->lessonBlock->supportsSubmissions() || $block->lessonBlock->attemptProvider() !== null);

        $items = collect();

        foreach ($sessionLessonBlocks as $block) {
            foreach ($block->submissions->sortByDesc('attempt_number')->unique('student_id') as $submission) {
                $items->push($this->describeSubmission($block, $submission));
            }

            foreach ($block->attempts->sortByDesc('attempt_number')->unique('student_id') as $attempt) {
                $items->push($this->describeAttempt($block, $attempt));
            }
        }

        return $this->itemsCache = $items->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function describeSubmission(SessionLessonBlock $block, Submission $submission): array
    {
        $maxScore = $block->lessonBlock->learningActivity()?->max_score;
        $percentage = $submission->score !== null && $maxScore > 0
            ? round((float) $submission->score / (float) $maxScore * 100, 2)
            : null;

        return [
            'student_id' => $submission->student_id,
            'student_name' => trim("{$submission->student->first_name} {$submission->student->last_name}"),
            'session_lesson_block_id' => $block->id,
            'lesson_block_id' => $block->lesson_block_id,
            'kind' => 'submission',
            'block_type' => $block->lessonBlock->block_type->value,
            'title' => $this->titleFor($block->lessonBlock, true),
            'lesson_title' => $block->sessionLesson->lesson->title,
            'status' => $submission->status->value,
            'status_label' => $this->statusLabel($submission->status->value),
            'score' => $submission->score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $submission->passed,
            'submitted_at' => $submission->submitted_at,
            'resolved_at' => $submission->reviewed_at,
            'activity_at' => $submission->submitted_at ?? $submission->created_at,
            'published_at' => $submission->published_at,
            'url' => "/tutor/submissions/{$submission->id}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeAttempt(SessionLessonBlock $block, Attempt $attempt): array
    {
        return [
            'student_id' => $attempt->student_id,
            'student_name' => trim("{$attempt->student->first_name} {$attempt->student->last_name}"),
            'session_lesson_block_id' => $block->id,
            'lesson_block_id' => $block->lesson_block_id,
            'kind' => 'attempt',
            'block_type' => $block->lessonBlock->block_type->value,
            'title' => $this->titleFor($block->lessonBlock, false),
            'lesson_title' => $block->sessionLesson->lesson->title,
            'status' => $attempt->status->value,
            'status_label' => $this->statusLabel($attempt->status->value),
            'score' => $attempt->raw_score,
            'max_score' => $attempt->max_score,
            'percentage' => $attempt->percentage,
            'passed' => $attempt->passed,
            'submitted_at' => $attempt->completed_at,
            'resolved_at' => $attempt->completed_at,
            'activity_at' => $attempt->completed_at ?? $attempt->started_at,
            // Attempts are auto-scored and have no publish step to track —
            // this stays null so "awaiting published feedback" (a
            // Submission-only concept) never matches an attempt.
            'published_at' => null,
            'url' => "/tutor/attempts/{$attempt->id}",
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

    /**
     * Session Lesson Blocks the tutor marked Required for completion, whose
     * availability window has already closed, paired with the students
     * booked into that session who never submitted anything at all — a gap
     * the shared items() dataset can't see, since it only carries records
     * that exist.
     *
     * @return Collection<int, list<array{title: string, url: string}>>
     */
    private function missingHomeworkByStudent(TutorProfile $tutor): Collection
    {
        $blocks = SessionLessonBlock::whereHas(
            'sessionLesson.teachingSession',
            fn ($query) => $query->where('tutor_profile_id', $tutor->id),
        )
            ->where('completion_mode', SessionLessonBlockCompletionMode::Required)
            ->with(['lessonBlock', 'sessionLesson.teachingSession.bookings.student', 'submissions'])
            ->get()
            ->filter(fn (SessionLessonBlock $block) => $block->lessonBlock->supportsSubmissions() && ! $block->isAvailable());

        $missing = collect();

        foreach ($blocks as $block) {
            $submittedStudentIds = $block->submissions->pluck('student_id')->unique();
            $bookedStudents = $block->sessionLesson->teachingSession->bookings
                ->where('status', BookingStatus::Confirmed)
                ->pluck('student')
                ->filter()
                ->unique('id');

            foreach ($bookedStudents as $student) {
                if ($submittedStudentIds->contains($student->id)) {
                    continue;
                }

                $existing = $missing->get($student->id, []);
                $existing[] = [
                    'title' => $block->lessonBlock->title ?? $block->lessonBlock->learningActivity()?->title ?? 'Untitled activity',
                    'url' => "/tutor/session-lesson-blocks/{$block->id}/submissions",
                ];
                $missing->put($student->id, $existing);
            }
        }

        return $missing;
    }

    /**
     * Booked students whose session's scheduled end time has already
     * passed while the session itself never transitioned out of
     * Scheduled — a proxy for "possibly missed," since there's no
     * dedicated attendance flag to read instead.
     *
     * @return Collection<int, true>
     */
    private function missedSessionsByStudent(TutorProfile $tutor): Collection
    {
        $sessions = $tutor->teachingSessions()
            ->where('status', SessionStatus::Scheduled)
            ->whereDate('date', '<', Carbon::today())
            ->with('bookings')
            ->get();

        $missed = collect();

        foreach ($sessions as $session) {
            foreach ($session->bookings->where('status', BookingStatus::Confirmed) as $booking) {
                $missed->put($booking->student_id, true);
            }
        }

        return $missed;
    }
}
