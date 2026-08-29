<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CourseManagementResource;
use App\Models\SelfPacedCourse;
use App\Services\Admin\AdminCourseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfPacedCourseManagementController extends Controller
{
    public function __construct(private readonly AdminCourseManagementService $courses) {}

    public function index(Request $request): JsonResponse
    {
        $courses = $this->courses->list($request->only(['status', 'search']), (int) $request->integer('per_page', 15));

        return response()->json([
            'courses' => CourseManagementResource::collection($courses->items()),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    public function show(SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        return response()->json(['course' => $this->courses->detail($selfPacedCourse)]);
    }
}
