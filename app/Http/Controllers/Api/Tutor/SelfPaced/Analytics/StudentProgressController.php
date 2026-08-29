<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use App\Services\SelfPaced\Analytics\StudentProgressService;
use Illuminate\Http\JsonResponse;

class StudentProgressController extends Controller
{
    /**
     * The full learning-journey report for one enrolled student: profile,
     * progress summary, chapter/lesson-block breakdown, and timeline.
     */
    public function show(SelfPacedCourse $selfPacedCourse, Enrollment $enrollment, StudentProgressService $progress): JsonResponse
    {
        $this->authorize('view', $selfPacedCourse);
        abort_unless($enrollment->self_paced_course_id === $selfPacedCourse->id, 404);

        return response()->json([
            'student_progress' => $progress->detail($enrollment),
        ]);
    }
}
