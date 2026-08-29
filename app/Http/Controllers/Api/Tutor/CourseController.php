<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ArchiveCourseRequest;
use App\Http\Requests\Tutor\DestroyCourseRequest;
use App\Http\Requests\Tutor\StoreCourseRequest;
use App\Http\Requests\Tutor\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    /**
     * The relations eager-loaded on every course response.
     *
     * @var list<string>
     */
    private const WITH = ['curriculum', 'grade', 'subject'];

    /**
     * Return the logged in tutor's courses.
     */
    public function index(Request $request): JsonResponse
    {
        $courses = $request->user()->tutorProfile
            ->courses()
            ->with(self::WITH)
            ->latest()
            ->get();

        return response()->json([
            'courses' => CourseResource::collection($courses),
        ]);
    }

    /**
     * Create a new course for the logged in tutor.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = $request->user()->tutorProfile
            ->courses()
            ->create([
                ...$request->safe()->except(['thumbnail', 'cover_image', 'status']),
                'thumbnail_path' => $request->file('thumbnail')?->store('course-thumbnails', 'public'),
                'cover_image_path' => $request->file('cover_image')?->store('course-covers', 'public'),
                'status' => $request->validated('status') ?? CourseStatus::Draft->value,
            ]);

        return response()->json([
            'course' => new CourseResource($course->load(self::WITH)),
        ], 201);
    }

    /**
     * Show a single course belonging to the logged in tutor.
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'course' => new CourseResource($course->load(self::WITH)),
        ]);
    }

    /**
     * Update an existing course belonging to the logged in tutor.
     */
    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail_path) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }

            $course->thumbnail_path = $request->file('thumbnail')->store('course-thumbnails', 'public');
        }

        if ($request->hasFile('cover_image')) {
            if ($course->cover_image_path) {
                Storage::disk('public')->delete($course->cover_image_path);
            }

            $course->cover_image_path = $request->file('cover_image')->store('course-covers', 'public');
        }

        $course->fill($request->safe()->except(['thumbnail', 'cover_image']));
        $course->save();

        return response()->json([
            'course' => new CourseResource($course->load(self::WITH)),
        ]);
    }

    /**
     * Delete a draft course belonging to the logged in tutor.
     */
    public function destroy(DestroyCourseRequest $request, Course $course): JsonResponse
    {
        if ($course->thumbnail_path) {
            Storage::disk('public')->delete($course->thumbnail_path);
        }

        if ($course->cover_image_path) {
            Storage::disk('public')->delete($course->cover_image_path);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted.',
        ]);
    }

    /**
     * Archive a course, removing it from active management.
     */
    public function archive(ArchiveCourseRequest $request, Course $course): JsonResponse
    {
        $course->update(['status' => CourseStatus::Archived]);

        return response()->json([
            'course' => new CourseResource($course->load(self::WITH)),
        ]);
    }
}
