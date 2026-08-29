<?php

namespace App\Policies;

use App\Models\SelfPacedCourse;
use App\Models\User;

class SelfPacedCoursePolicy
{
    /**
     * Determine whether the user may view this course's tutor-facing
     * analytics — every enrollment/certificate/assessment nested under it
     * belongs to the same owning tutor, so this single check is the gate
     * for the whole analytics module.
     */
    public function view(User $user, SelfPacedCourse $course): bool
    {
        return $course->tutor_profile_id === $user->tutorProfile?->id;
    }
}
