<?php

namespace App\Services\Commerce;

use App\Enums\EnrollmentStatus;
use App\Enums\ProductType;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\SelfPacedCourse;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class EnrollmentService
{
    /**
     * Grant access for every course-offering line item on a paid order.
     * Idempotent: safe to call more than once for the same order (the ITN
     * handler's own idempotency guard should prevent that in practice, but
     * the unique index + firstOrCreate here is the airtight backstop).
     */
    public function activate(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_type !== ProductType::CourseOffering->value) {
                continue;
            }

            try {
                Enrollment::query()->firstOrCreate(
                    [
                        'student_id' => $order->student_id,
                        'self_paced_course_id' => $item->product_id,
                    ],
                    [
                        'order_id' => $order->id,
                        'status' => EnrollmentStatus::Active,
                        'enrolled_at' => now(),
                        // Captured at enrollment time so future progress can
                        // be reconciled against the course version it was
                        // actually earned against — see SelfPacedCourse::$course_version.
                        'course_version' => SelfPacedCourse::find($item->product_id)?->course_version ?? 1,
                    ],
                );
            } catch (QueryException) {
                // A concurrent request already created this enrollment —
                // the student owns the course either way, nothing to do.
            }
        }
    }

    /**
     * Whether the student owns this course — Active (currently learning) or
     * Completed (finished, but still entitled to revisit content/replay
     * certificates) both count. Only Cancelled excludes access.
     */
    public function hasAccess(User $student, SelfPacedCourse $course): bool
    {
        return $this->findAccessible($student, $course) !== null;
    }

    /**
     * The student's Active/Completed enrollment for this course, if any —
     * what every student-facing learning-delivery endpoint authorizes
     * against, since (unlike the boolean hasAccess()) they also need the
     * Enrollment row itself to record/read progress against.
     */
    public function findAccessible(User $student, SelfPacedCourse $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('self_paced_course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->first();
    }

    /**
     * Bulk ownership lookup for a set of course ids — used by the
     * marketplace listing/details endpoints to compute `is_enrolled`
     * without an N+1 query per course card.
     *
     * @param  iterable<int>  $courseIds
     * @return Collection<int, int>
     */
    public function enrolledCourseIds(User $student, iterable $courseIds): Collection
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->whereIn('self_paced_course_id', collect($courseIds)->all())
            ->pluck('self_paced_course_id');
    }
}
