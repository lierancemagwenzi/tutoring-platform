<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Commerce\EnrollmentCardResource;
use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use App\Services\Commerce\EnrollmentService;
use App\Services\LearnerProgress\CourseProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /**
     * The logged in student's course enrollments — "My Courses". Includes
     * both Active (in progress) and Completed courses; only Cancelled ones
     * are excluded.
     */
    public function index(Request $request, CourseProgressService $progress): JsonResponse
    {
        $enrollments = $request->user()->enrollments()
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->with(['course.subject', 'course.grade', 'course.tutorProfile', 'certificate'])
            ->latest('enrolled_at')
            ->get();

        // Bounded by this one student's own course list (never a public,
        // paginated grid), so a per-enrollment progress computation here is
        // fine — unlike the marketplace catalog, which bulk-loads
        // `is_enrolled` for exactly that N+1 reason.
        $enrollments->each(fn (Enrollment $enrollment) => $enrollment->setAttribute('progress', $progress->summarize($enrollment)));

        return response()->json([
            'enrollments' => EnrollmentCardResource::collection($enrollments),
        ]);
    }

    /**
     * Whether the logged in student currently owns the given course — a
     * lightweight gate for future course-content-viewing work to depend on.
     */
    public function access(Request $request, SelfPacedCourse $selfPacedCourse, EnrollmentService $enrollments): JsonResponse
    {
        return response()->json([
            'enrolled' => $enrollments->hasAccess($request->user(), $selfPacedCourse),
        ]);
    }

    /**
     * Show a single enrollment belonging to the logged in student.
     */
    public function show(Enrollment $enrollment, CourseProgressService $progress): JsonResponse
    {
        $this->authorize('view', $enrollment);

        $enrollment->load(['course.subject', 'course.grade', 'course.tutorProfile', 'certificate']);
        $enrollment->setAttribute('progress', $progress->summarize($enrollment));

        return response()->json([
            'enrollment' => new EnrollmentCardResource($enrollment),
        ]);
    }
}
