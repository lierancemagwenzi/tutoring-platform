<?php

namespace App\Services\LearnerProgress;

use App\Models\ActivityProgress;
use App\Models\Enrollment;
use App\Models\SelfPacedActivity;

/**
 * Owns per-activity (simple content block) completion for a student's
 * enrollment. Every completion triggers a re-evaluation of the owning
 * module (and, transitively, the whole course) — that's the entire
 * mechanism behind "automatically unlock the next chapter" and
 * "automatically mark course completed": nothing explicitly unlocks
 * anything, completion facts just accumulate and read-time state
 * computation reacts to them.
 */
class ActivityProgressService
{
    public function __construct(
        private readonly ModuleProgressService $moduleProgress,
        private readonly CourseCompletionService $courseCompletion,
    ) {}

    /**
     * Record that a student has opened an activity, for "last activity"/
     * analytics purposes. Validates the owning module is unlocked.
     */
    public function recordViewed(Enrollment $enrollment, SelfPacedActivity $activity): ActivityProgress
    {
        $this->moduleProgress->assertUnlocked($enrollment, $activity->module);

        $progress = ActivityProgress::firstOrCreate(
            ['enrollment_id' => $enrollment->id, 'self_paced_activity_id' => $activity->id],
            ['started_at' => now()],
        );

        $enrollment->update(['last_accessed_at' => now()]);

        return $progress;
    }

    /**
     * Mark an activity complete — either the tutor-configured auto-complete
     * path or an explicit "Mark Complete" click; the caller decides which,
     * this service doesn't distinguish between them. Idempotent.
     */
    public function markComplete(Enrollment $enrollment, SelfPacedActivity $activity, ?int $timeSpentSeconds = null): ActivityProgress
    {
        $this->moduleProgress->assertUnlocked($enrollment, $activity->module);

        $progress = ActivityProgress::firstOrCreate(
            ['enrollment_id' => $enrollment->id, 'self_paced_activity_id' => $activity->id],
            ['started_at' => now()],
        );

        if ($progress->completed_at === null) {
            $progress->update(array_filter([
                'completed_at' => now(),
                'time_spent_seconds' => $timeSpentSeconds,
            ], fn ($value) => $value !== null));
        }

        $enrollment->update(['last_accessed_at' => now()]);

        $this->moduleProgress->evaluate($enrollment, $activity->module);
        $this->courseCompletion->evaluate($enrollment);

        return $progress->fresh();
    }
}
