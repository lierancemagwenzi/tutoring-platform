<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderLessonsRequest;
use App\Http\Requests\Tutor\StoreLessonRequest;
use App\Http\Requests\Tutor\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Chapter;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    /**
     * Return the lessons belonging to a chapter. Passing `service_id` (the
     * lesson-picker's scheduling context) narrows this to published
     * lessons only — the same status ScheduleSessionRequest/
     * AssignSessionLessonRequest require before a lesson can be assigned.
     */
    public function index(Request $request, Chapter $chapter): JsonResponse
    {
        abort_unless($chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $lessons = $chapter->lessons()
            ->when($request->filled('service_id'), fn ($query) => $query->where('status', LessonStatus::Published))
            ->get();

        return response()->json([
            'lessons' => LessonResource::collection($lessons),
        ]);
    }

    /**
     * Create a new lesson for a chapter.
     */
    public function store(StoreLessonRequest $request, Chapter $chapter): JsonResponse
    {
        $lesson = $chapter->lessons()->create([
            ...$request->safe()->except('status'),
            'position' => $chapter->lessons()->count(),
            'status' => $request->validated('status') ?? LessonStatus::Draft->value,
        ]);

        return response()->json([
            'lesson' => new LessonResource($lesson),
        ], 201);
    }

    /**
     * Update an existing lesson.
     */
    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $lesson->update($request->validated());

        return response()->json([
            'lesson' => new LessonResource($lesson),
        ]);
    }

    /**
     * Delete a lesson.
     */
    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $lesson->delete();

        return response()->json([
            'message' => 'Lesson deleted.',
        ]);
    }

    /**
     * Persist the drag-and-drop order of a chapter's lessons.
     */
    public function reorder(ReorderLessonsRequest $request, Chapter $chapter): JsonResponse
    {
        foreach ($request->validated('lesson_ids') as $position => $lessonId) {
            Lesson::whereKey($lessonId)->update(['position' => $position]);
        }

        return response()->json([
            'lessons' => LessonResource::collection($chapter->lessons()->get()),
        ]);
    }
}
