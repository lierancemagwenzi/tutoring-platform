<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use App\Services\SelfPaced\Analytics\AssessmentAnalyticsService;
use Illuminate\Http\JsonResponse;

class StudentAssessmentsController extends Controller
{
    /**
     * Every assessment attempt this student has made in this course,
     * grouped by assessment with best/latest score.
     */
    public function index(SelfPacedCourse $selfPacedCourse, Enrollment $enrollment, AssessmentAnalyticsService $assessments): JsonResponse
    {
        $this->authorize('view', $selfPacedCourse);
        abort_unless($enrollment->self_paced_course_id === $selfPacedCourse->id, 404);

        return response()->json([
            'assessments' => $assessments->attemptsFor($enrollment),
        ]);
    }
}
