<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced\Analytics;

use App\Http\Controllers\Controller;
use App\Models\SelfPacedCourse;
use App\Services\SelfPaced\Analytics\CourseAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseAnalyticsController extends Controller
{
    /**
     * The course-level analytics overview: enrollment counts, completion
     * rate, average progress/assessment score, certificates issued —
     * covers both the "Course Overview" and "Learning Analytics" sections
     * of the tutor dashboard, since their field lists are the same numbers.
     */
    public function show(Request $request, SelfPacedCourse $selfPacedCourse, CourseAnalyticsService $analytics): JsonResponse
    {
        $this->authorize('view', $selfPacedCourse);

        return response()->json([
            'course' => [
                'id' => $selfPacedCourse->id,
                'title' => $selfPacedCourse->title,
                'status' => $selfPacedCourse->status->value,
            ],
            'analytics' => $analytics->overview($selfPacedCourse),
        ]);
    }
}
