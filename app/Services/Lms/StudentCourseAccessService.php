<?php

namespace App\Services\Lms;

use App\Enums\BookingStatus;
use App\Enums\CourseStatus;
use App\Models\Booking;
use App\Models\Course;
use App\Models\User;

class StudentCourseAccessService
{
    /**
     * Determine whether a student may access a published course's content.
     *
     * The course itself must be published, and the student must have a paid
     * booking for a service matching the course's subject, grade, and
     * curriculum. Callers are responsible for additionally checking the
     * publish status of whatever chapter/lesson/block they are guarding.
     */
    public function canAccess(User $student, Course $course): bool
    {
        if ($course->status !== CourseStatus::Published) {
            return false;
        }

        return Booking::query()
            ->where('student_id', $student->id)
            ->where('status', BookingStatus::Confirmed)
            ->whereHas('service', function ($query) use ($course) {
                $query->where('subject_id', $course->subject_id)
                    // A service with no grade requirement (grade_id is null)
                    // matches a course of any grade — see Course::matchesService().
                    ->where(function ($query) use ($course) {
                        $query->whereNull('grade_id')->orWhere('grade_id', $course->grade_id);
                    })
                    ->whereHas('curricula', function ($query) use ($course) {
                        $query->whereKey($course->curriculum_id);
                    });
            })
            ->exists();
    }
}
