<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedCourse;

/**
 * The publish-readiness rules for a self-paced course. Centralized here so
 * both the publish endpoint (blocks the transition) and the course editor
 * (shows a checklist before the tutor even tries) read the same source of
 * truth.
 */
class SelfPacedPublishingService
{
    /**
     * @return list<string>
     */
    public function errors(SelfPacedCourse $course): array
    {
        $errors = [];
        $modules = $course->modules()->with(['activities', 'assessments'])->get();

        if ($modules->isEmpty()) {
            $errors[] = 'The course must have at least one module.';
        }

        foreach ($modules as $module) {
            if ($module->activities->isEmpty() && $module->assessments->isEmpty()) {
                $errors[] = "Module \"{$module->title}\" has no content.";

                continue;
            }

            foreach ($module->activities->where('required', true) as $activity) {
                if (! $activity->hasRequiredContent()) {
                    $errors[] = "Activity \"{$activity->title}\" in module \"{$module->title}\" is missing required content.";
                }
            }

            foreach ($module->assessments->where('required', true) as $assessment) {
                if (! $assessment->isConfigured()) {
                    $errors[] = "Assessment \"{$assessment->title}\" in module \"{$module->title}\" has no provider configured.";
                }
            }
        }

        if ($course->price === null) {
            $errors[] = 'Set a price for the course.';
        }

        if ($course->currency === null) {
            $errors[] = 'Select a currency for the course.';
        }

        return $errors;
    }

    public function canPublish(SelfPacedCourse $course): bool
    {
        return $this->errors($course) === [];
    }
}
