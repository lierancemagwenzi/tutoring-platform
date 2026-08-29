<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\AssignSessionLessonRequest;
use App\Http\Requests\Tutor\ReorderSessionLessonsRequest;
use App\Http\Resources\SessionLessonResource;
use App\Models\Lesson;
use App\Models\SessionLesson;
use App\Models\TeachingSession;
use App\Services\Sessions\SessionContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionLessonController extends Controller
{
    public function __construct(protected SessionContentService $service) {}

    /**
     * List the lessons assigned to a session, in order.
     */
    public function index(Request $request, TeachingSession $session): JsonResponse
    {
        abort_unless($session->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $sessionLessons = SessionLesson::where('teaching_session_id', $session->id)
            ->withCount('sessionLessonBlocks')
            ->with('lesson')
            ->orderBy('position')
            ->get();

        return response()->json([
            'session_lessons' => SessionLessonResource::collection($sessionLessons),
        ]);
    }

    /**
     * Assign an existing lesson to a session. Lessons are only ever
     * referenced here, never duplicated.
     */
    public function store(AssignSessionLessonRequest $request, TeachingSession $session): JsonResponse
    {
        $lesson = Lesson::findOrFail($request->validated('lesson_id'));

        abort_unless($lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $sessionLesson = $this->service->assignLesson($session, $lesson);

        return response()->json([
            'session_lesson' => new SessionLessonResource($sessionLesson->load('lesson')->loadCount('sessionLessonBlocks')),
        ], 201);
    }

    /**
     * Remove a lesson from a session. The underlying Lesson is untouched.
     */
    public function destroy(Request $request, TeachingSession $session, SessionLesson $sessionLesson): JsonResponse
    {
        abort_unless($session->tutor_profile_id === $request->user()->tutorProfile?->id, 403);
        abort_unless($sessionLesson->teaching_session_id === $session->id, 404);

        $this->service->removeLesson($sessionLesson);

        return response()->json([
            'message' => 'Lesson removed from session.',
        ]);
    }

    /**
     * Persist the drag-and-drop order of a session's assigned lessons.
     */
    public function reorder(ReorderSessionLessonsRequest $request, TeachingSession $session): JsonResponse
    {
        $this->service->reorderLessons($session, $request->validated('session_lesson_ids'));

        $sessionLessons = SessionLesson::where('teaching_session_id', $session->id)
            ->withCount('sessionLessonBlocks')
            ->with('lesson')
            ->orderBy('position')
            ->get();

        return response()->json([
            'session_lessons' => SessionLessonResource::collection($sessionLessons),
        ]);
    }
}
