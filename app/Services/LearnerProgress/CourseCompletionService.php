<?php

namespace App\Services\LearnerProgress;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;

/**
 * The single place that decides a course is finished. Called after every
 * completion-triggering event (an activity marked complete, an assessment
 * attempt passed) — cheap and safe to call redundantly, since it's a no-op
 * once the enrollment is already Completed.
 */
class CourseCompletionService
{
    public function __construct(
        private readonly ModuleProgressService $moduleProgress,
        private readonly CertificateService $certificates,
    ) {}

    public function evaluate(Enrollment $enrollment): void
    {
        if ($enrollment->status === EnrollmentStatus::Completed) {
            return;
        }

        $course = $enrollment->course()->with(['modules.activities', 'modules.assessments'])->first();

        if ($course->modules->isEmpty()) {
            return;
        }

        $allModulesCompleted = $course->modules->every(
            fn ($module) => $this->moduleProgress->isCompleted($enrollment, $module),
        );

        if (! $allModulesCompleted) {
            return;
        }

        $enrollment->update([
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->certificates->generate($enrollment->fresh());
    }
}
