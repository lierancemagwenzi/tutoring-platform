<?php

namespace App\Services\TutorWorkspace;

use App\Enums\SessionLessonBlockAvailabilityMode;
use App\Models\SessionLessonBlock;
use App\Models\TutorProfile;
use Illuminate\Support\Carbon;

/**
 * The Content domain of the Tutor Workspace — Session Lesson Blocks whose
 * Delivery Configuration needs tutor action. Reads SessionLessonBlock's own
 * availability_mode/is_manually_released/available_from/available_until/
 * opens_at/closes_at fields directly; never recomputes availability rules
 * that already live on the model (see SessionLessonBlock::isAvailable()).
 */
class ContentReleaseHubService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function needsAction(TutorProfile $tutor, int $withinHours = 24): array
    {
        $blocks = SessionLessonBlock::whereHas(
            'sessionLesson.teachingSession',
            fn ($query) => $query->where('tutor_profile_id', $tutor->id),
        )
            ->with(['lessonBlock', 'sessionLesson.lesson', 'sessionLesson.teachingSession'])
            ->get();

        $now = Carbon::now();
        $horizon = $now->copy()->addHours($withinHours);
        $items = [];

        foreach ($blocks as $block) {
            foreach ($this->reasonsFor($block, $now, $horizon) as [$reason, $reasonLabel]) {
                $items[] = $this->describe($block, $reason, $reasonLabel);
            }
        }

        return $items;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function reasonsFor(SessionLessonBlock $block, Carbon $now, Carbon $horizon): array
    {
        $reasons = [];

        if ($block->availability_mode === SessionLessonBlockAvailabilityMode::ManualRelease && ! $block->is_manually_released) {
            $reasons[] = ['manual_release_pending', 'Manual release pending'];
        }

        if ($block->availability_mode === SessionLessonBlockAvailabilityMode::ScheduledRelease
            && $block->available_from?->isToday()) {
            $reasons[] = ['scheduled_release_today', 'Scheduled release today'];
        }

        if ($block->availability_mode === SessionLessonBlockAvailabilityMode::AssessmentWindow) {
            if ($block->opens_at?->between($now, $horizon)) {
                $reasons[] = ['window_opening', 'Assessment window opening soon'];
            }

            if ($block->closes_at?->between($now, $horizon)) {
                $reasons[] = ['window_closing', 'Assessment window closing soon'];
            }
        }

        return $reasons;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(SessionLessonBlock $block, string $reason, string $reasonLabel): array
    {
        return [
            'session_lesson_block_id' => $block->id,
            'title' => $block->lessonBlock->title ?? ucfirst(str_replace('_', ' ', $block->lessonBlock->block_type->value)),
            'lesson_title' => $block->sessionLesson->lesson->title,
            'session_id' => $block->sessionLesson->teachingSession->id,
            'reason' => $reason,
            'reason_label' => $reasonLabel,
            'manage_url' => "/tutor/session-lessons/{$block->session_lesson_id}/blocks",
            'session_url' => "/tutor/sessions/{$block->sessionLesson->teachingSession->id}",
        ];
    }
}
