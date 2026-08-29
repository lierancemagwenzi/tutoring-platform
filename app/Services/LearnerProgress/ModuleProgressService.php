<?php

namespace App\Services\LearnerProgress;

use App\Models\ActivityProgress;
use App\Models\Enrollment;
use App\Models\ModuleProgress;
use App\Models\SelfPacedAssessmentAttempt;
use App\Models\SelfPacedModule;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Owns per-module completion for a student's enrollment. Completion is a
 * durable, one-way fact written to ModuleProgress the moment it's first
 * earned (evaluate()) — never silently recomputed from the authoring
 * SelfPacedActivity/SelfPacedAssessment "required" flags on every read.
 * That's what keeps this version-safe: a tutor editing a module's content
 * after a student has already completed it can never retroactively
 * un-complete that student's progress.
 *
 * "Locked / Current / Completed" chapter state, by contrast, IS computed
 * at read time — it's a pure function of the (stable, already-written)
 * completion facts in position order, so there's nothing to store or drift.
 */
class ModuleProgressService
{
    /**
     * Re-evaluate a module's completion after a completion-triggering event
     * (an activity or assessment was just completed). Idempotent — once
     * completed_at is set it is never cleared or recomputed.
     */
    public function evaluate(Enrollment $enrollment, SelfPacedModule $module): ModuleProgress
    {
        $progress = ModuleProgress::firstOrCreate(
            ['enrollment_id' => $enrollment->id, 'self_paced_module_id' => $module->id],
            ['started_at' => now()],
        );

        if ($progress->completed_at === null && $this->meetsCompletionCriteria($enrollment, $module)) {
            $progress->update(['completed_at' => now()]);
        }

        return $progress->fresh();
    }

    /**
     * Whether this module has already earned its durable completion fact.
     */
    public function isCompleted(Enrollment $enrollment, SelfPacedModule $module): bool
    {
        return ModuleProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('self_paced_module_id', $module->id)
            ->whereNotNull('completed_at')
            ->exists();
    }

    /**
     * The display state of every module in a course, in position order.
     * Strictly sequential: the first not-yet-completed module is "current",
     * everything after it is "locked", nothing beyond Current is ever
     * simultaneously "unlocked" under this progression model.
     *
     * @param  Collection<int, SelfPacedModule>  $modules
     * @return array<int, string> module id => 'completed'|'current'|'locked'
     */
    public function statesFor(Enrollment $enrollment, Collection $modules): array
    {
        $states = [];
        $reachedCurrent = false;

        foreach ($modules as $module) {
            if ($this->isCompleted($enrollment, $module)) {
                $states[$module->id] = 'completed';
            } elseif (! $reachedCurrent) {
                $states[$module->id] = 'current';
                $reachedCurrent = true;
            } else {
                $states[$module->id] = 'locked';
            }
        }

        return $states;
    }

    public function stateFor(Enrollment $enrollment, SelfPacedModule $module): string
    {
        return $this->statesFor($enrollment, $module->course->modules)[$module->id] ?? 'locked';
    }

    /**
     * Guard content access: a locked module's activities/assessments may
     * never be opened, regardless of direct-link/ID guessing.
     */
    public function assertUnlocked(Enrollment $enrollment, SelfPacedModule $module): void
    {
        if ($this->stateFor($enrollment, $module) === 'locked') {
            throw new RuntimeException('This chapter is locked.');
        }
    }

    /**
     * Record that a student has opened an unlocked module, for "current
     * chapter"/last-activity purposes. A no-op if already started.
     */
    public function ensureStarted(Enrollment $enrollment, SelfPacedModule $module): ModuleProgress
    {
        $this->assertUnlocked($enrollment, $module);

        return ModuleProgress::firstOrCreate(
            ['enrollment_id' => $enrollment->id, 'self_paced_module_id' => $module->id],
            ['started_at' => now()],
        );
    }

    /**
     * Every required activity completed (if the module gates on activities)
     * and every required assessment passed (if it gates on assessments).
     * Optional (required = false) content never factors in either way.
     */
    private function meetsCompletionCriteria(Enrollment $enrollment, SelfPacedModule $module): bool
    {
        $activitiesOk = ! $module->activity_completion_required || $this->requiredActivitiesCompleted($enrollment, $module);
        $assessmentsOk = ! $module->assessment_completion_required || $this->requiredAssessmentsPassed($enrollment, $module);

        return $activitiesOk && $assessmentsOk;
    }

    private function requiredActivitiesCompleted(Enrollment $enrollment, SelfPacedModule $module): bool
    {
        $requiredIds = $module->activities->where('required', true)->pluck('id');

        if ($requiredIds->isEmpty()) {
            return true;
        }

        $completedIds = ActivityProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('self_paced_activity_id', $requiredIds)
            ->whereNotNull('completed_at')
            ->pluck('self_paced_activity_id');

        return $requiredIds->diff($completedIds)->isEmpty();
    }

    private function requiredAssessmentsPassed(Enrollment $enrollment, SelfPacedModule $module): bool
    {
        $requiredIds = $module->assessments->where('required', true)->pluck('id');

        if ($requiredIds->isEmpty()) {
            return true;
        }

        $passedIds = SelfPacedAssessmentAttempt::query()
            ->where('student_id', $enrollment->student_id)
            ->whereIn('self_paced_assessment_id', $requiredIds)
            ->where('passed', true)
            ->pluck('self_paced_assessment_id');

        return $requiredIds->diff($passedIds)->isEmpty();
    }
}
