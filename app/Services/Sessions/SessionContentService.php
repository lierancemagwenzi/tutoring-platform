<?php

namespace App\Services\Sessions;

use App\Enums\AttemptsMode;
use App\Enums\SessionLessonBlockAvailabilityMode;
use App\Enums\SessionLessonBlockCompletionMode;
use App\Enums\SessionLessonBlockVisibility;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\SessionLesson;
use App\Models\SessionLessonBlock;
use App\Models\TeachingSession;
use Illuminate\Support\Facades\DB;

class SessionContentService
{
    /**
     * Assign an existing lesson to a session, appended after any already-assigned lessons.
     */
    public function assignLesson(TeachingSession $session, Lesson $lesson): SessionLesson
    {
        $position = SessionLesson::where('teaching_session_id', $session->id)->count();

        return SessionLesson::create([
            'teaching_session_id' => $session->id,
            'lesson_id' => $lesson->id,
            'position' => $position,
        ]);
    }

    public function removeLesson(SessionLesson $sessionLesson): void
    {
        $sessionLesson->delete();
    }

    /**
     * @param  list<int>  $sessionLessonIds
     */
    public function reorderLessons(TeachingSession $session, array $sessionLessonIds): void
    {
        DB::transaction(function () use ($sessionLessonIds) {
            foreach ($sessionLessonIds as $position => $sessionLessonId) {
                SessionLesson::whereKey($sessionLessonId)->update(['position' => $position]);
            }
        });
    }

    /**
     * Explicitly assign a lesson block so it can become available to students —
     * blocks are never assigned automatically just because their lesson is.
     *
     * @param  array<string, mixed>  $delivery
     */
    public function assignBlock(SessionLesson $sessionLesson, LessonBlock $block, array $delivery): SessionLessonBlock
    {
        return SessionLessonBlock::create(array_merge(
            [
                'session_lesson_id' => $sessionLesson->id,
                'lesson_block_id' => $block->id,
            ],
            $this->deliveryAttributes($delivery),
        ));
    }

    /**
     * @param  array<string, mixed>  $delivery
     */
    public function updateBlockAvailability(SessionLessonBlock $sessionLessonBlock, array $delivery): SessionLessonBlock
    {
        $sessionLessonBlock->update($this->deliveryAttributes($delivery, $sessionLessonBlock));

        return $sessionLessonBlock;
    }

    public function removeBlock(SessionLessonBlock $sessionLessonBlock): void
    {
        $sessionLessonBlock->delete();
    }

    /**
     * Computes the full set of delivery-configuration attributes for a
     * Session Lesson Block — the behavioural layer that determines how a
     * Lesson Block acts within this specific session, entirely separate
     * from the Lesson Block's own (reusable, delivery-agnostic) content.
     *
     * Fields irrelevant to the chosen availability/completion/attempts mode
     * are cleared, so stale configuration never lingers after a mode
     * switch. Any of the newer delivery fields omitted from $delivery (e.g.
     * a quick "release now" toggle that only sends availability fields)
     * fall back to $existing's current value on update, or a neutral
     * default when there is no existing record to fall back to.
     *
     * @param  array<string, mixed>  $delivery
     * @return array<string, mixed>
     */
    private function deliveryAttributes(array $delivery, ?SessionLessonBlock $existing = null): array
    {
        $availabilityMode = SessionLessonBlockAvailabilityMode::from($delivery['availability_mode']);

        $completionMode = SessionLessonBlockCompletionMode::from(
            $delivery['completion_mode'] ?? $existing?->completion_mode?->value ?? SessionLessonBlockCompletionMode::NotTracked->value,
        );

        $attemptsMode = AttemptsMode::from(
            $delivery['attempts_mode'] ?? $existing?->attempts_mode?->value ?? AttemptsMode::Unlimited->value,
        );

        $visibility = SessionLessonBlockVisibility::from(
            $delivery['visibility'] ?? $existing?->visibility?->value ?? SessionLessonBlockVisibility::Visible->value,
        );

        return [
            'availability_mode' => $availabilityMode,
            'available_from' => $availabilityMode === SessionLessonBlockAvailabilityMode::ScheduledRelease
                ? ($delivery['available_from'] ?? null) : null,
            'available_until' => $availabilityMode === SessionLessonBlockAvailabilityMode::ScheduledRelease
                ? ($delivery['available_until'] ?? null) : null,
            'opens_at' => $availabilityMode === SessionLessonBlockAvailabilityMode::AssessmentWindow
                ? ($delivery['opens_at'] ?? null) : null,
            'closes_at' => $availabilityMode === SessionLessonBlockAvailabilityMode::AssessmentWindow
                ? ($delivery['closes_at'] ?? null) : null,
            'is_manually_released' => $availabilityMode === SessionLessonBlockAvailabilityMode::ManualRelease
                ? (bool) ($delivery['is_manually_released'] ?? false) : false,
            'completion_mode' => $completionMode,
            'completion_rule' => $completionMode !== SessionLessonBlockCompletionMode::NotTracked
                ? ($delivery['completion_rule'] ?? $existing?->completion_rule?->value ?? null) : null,
            'attempts_mode' => $attemptsMode,
            'max_attempts' => $attemptsMode === AttemptsMode::Limited
                ? ($delivery['max_attempts'] ?? $existing?->max_attempts ?? null) : null,
            'passing_score' => $delivery['passing_score'] ?? $existing?->passing_score ?? null,
            'visibility' => $visibility,
        ];
    }
}
