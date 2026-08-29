<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\IndexSelfPacedCoursesRequest;
use App\Http\Resources\Marketplace\SelfPacedCourseCardResource;
use App\Http\Resources\Marketplace\SelfPacedCourseDetailsResource;
use App\Models\SelfPacedCourse;
use App\Services\Commerce\EnrollmentService;
use App\Services\Marketplace\SelfPacedCourseCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SelfPacedCourseController extends Controller
{
    public function __construct(
        protected SelfPacedCourseCatalogService $catalog,
        protected EnrollmentService $enrollments,
    ) {}

    /**
     * Search, filter, sort, and paginate published self-paced courses.
     */
    public function index(IndexSelfPacedCoursesRequest $request): JsonResponse
    {
        $courses = $this->catalog->search($request->validated(), $request->validated('per_page') ?? 12);

        $this->markEnrolled($request, collect($courses->items()));

        return response()->json([
            'courses' => SelfPacedCourseCardResource::collection($courses->items()),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * The distinct subject/language/tutor options actually available among
     * published courses, for the marketplace filter panel.
     */
    public function filters(): JsonResponse
    {
        return response()->json($this->catalog->filterOptions());
    }

    /**
     * Show a single published course's full details — modules, activities
     * and assessments are informational only here (no content/answers).
     */
    public function show(Request $request, int $selfPacedCourse): JsonResponse
    {
        $course = $this->catalog->findPublished($selfPacedCourse);

        abort_if($course === null, 404);

        $this->markEnrolled($request, collect([$course]));

        return response()->json([
            'course' => new SelfPacedCourseDetailsResource($course),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Sets an `is_enrolled` attribute on each course in a single bulk query,
     * rather than one ownership lookup per card — keeps the listing/details
     * endpoints free of N+1 queries regardless of grid size.
     *
     * @param  Collection<int, SelfPacedCourse>  $courses
     */
    private function markEnrolled(Request $request, Collection $courses): void
    {
        if (! $request->user() || $courses->isEmpty()) {
            return;
        }

        $enrolledIds = $this->enrollments->enrolledCourseIds($request->user(), $courses->pluck('id'));

        $courses->each(fn ($course) => $course->setAttribute('is_enrolled', $enrolledIds->contains($course->id)));
    }
}
