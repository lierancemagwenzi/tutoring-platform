<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ManageSelfPacedCourseRequest;
use App\Http\Requests\Tutor\SelfPaced\PublishSelfPacedCourseRequest;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedCourseRequest;
use App\Http\Requests\Tutor\SelfPaced\UpdateSelfPacedCourseRequest;
use App\Http\Resources\SelfPacedCourseResource;
use App\Models\SelfPacedCourse;
use App\Services\SelfPaced\SelfPacedCourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfPacedCourseController extends Controller
{
    public function __construct(protected SelfPacedCourseService $courses) {}

    /**
     * List the authenticated tutor's self-paced courses.
     */
    public function index(Request $request): JsonResponse
    {
        $courses = $request->user()->tutorProfile
            ->selfPacedCourses()
            ->withCount('modules')
            ->with(['subject', 'grade', 'category'])
            ->latest()
            ->get();

        return response()->json([
            'courses' => SelfPacedCourseResource::collection($courses),
        ]);
    }

    /**
     * Create a new self-paced course.
     */
    public function store(StoreSelfPacedCourseRequest $request): JsonResponse
    {
        $course = $this->courses->create($request->user()->tutorProfile, $request->validated());

        return response()->json([
            'course' => new SelfPacedCourseResource($course->load(['subject', 'grade', 'category'])),
        ], 201);
    }

    /**
     * Show a single self-paced course, with its full module/content tree.
     */
    public function show(Request $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        abort_unless($selfPacedCourse->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $selfPacedCourse->load(['subject', 'grade', 'category', 'modules.activities.attachments', 'modules.assessments', 'discountCodes']);

        return response()->json([
            'course' => new SelfPacedCourseResource($selfPacedCourse),
        ]);
    }

    /**
     * Update a self-paced course's General/Learning/Pricing settings.
     */
    public function update(UpdateSelfPacedCourseRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $course = $this->courses->update($selfPacedCourse, $request->validated());

        return response()->json([
            'course' => new SelfPacedCourseResource($course->load(['subject', 'grade', 'category'])),
        ]);
    }

    /**
     * Delete a self-paced course and its authored content.
     */
    public function destroy(ManageSelfPacedCourseRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $this->courses->delete($selfPacedCourse);

        return response()->json(['message' => 'Course deleted.']);
    }

    /**
     * Publish a course — blocked until SelfPacedPublishingService reports
     * no outstanding issues (see PublishSelfPacedCourseRequest).
     */
    public function publish(PublishSelfPacedCourseRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $course = $this->courses->publish($selfPacedCourse);

        return response()->json(['course' => new SelfPacedCourseResource($course)]);
    }

    /**
     * Move a published course back to Draft.
     */
    public function unpublish(ManageSelfPacedCourseRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $course = $this->courses->unpublish($selfPacedCourse);

        return response()->json(['course' => new SelfPacedCourseResource($course)]);
    }

    /**
     * Mark a course Private.
     */
    public function makePrivate(ManageSelfPacedCourseRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $course = $this->courses->makePrivate($selfPacedCourse);

        return response()->json(['course' => new SelfPacedCourseResource($course)]);
    }

    /**
     * Archive a course.
     */
    public function archive(ManageSelfPacedCourseRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $course = $this->courses->archive($selfPacedCourse);

        return response()->json(['course' => new SelfPacedCourseResource($course)]);
    }
}
