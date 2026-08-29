<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\Analytics\IndexCourseEnrollmentsRequest;
use App\Http\Resources\Tutor\SelfPaced\Analytics\TutorEnrollmentResource;
use App\Models\SelfPacedCourse;
use App\Services\SelfPaced\Analytics\EnrollmentAnalyticsService;
use Illuminate\Http\JsonResponse;

class CourseEnrollmentsController extends Controller
{
    /**
     * The paginated, filterable "Enrolled Students" list — also serves as
     * the "Completed Students" section via ?status=completed.
     */
    public function index(IndexCourseEnrollmentsRequest $request, SelfPacedCourse $selfPacedCourse, EnrollmentAnalyticsService $enrollments): JsonResponse
    {
        $this->authorize('view', $selfPacedCourse);

        $page = $enrollments->listForCourse(
            $selfPacedCourse,
            $request->validated(),
            $request->validated('per_page') ?? 15,
        );

        return response()->json([
            'enrollments' => TutorEnrollmentResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
