<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\AssignSessionLessonBlockRequest;
use App\Http\Requests\Tutor\UpdateSessionLessonBlockAvailabilityRequest;
use App\Http\Resources\LessonBlockResource;
use App\Http\Resources\SessionLessonBlockResource;
use App\Models\LessonBlock;
use App\Models\SessionLesson;
use App\Models\SessionLessonBlock;
use App\Services\Sessions\SessionContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionLessonBlockController extends Controller
{
    public function __construct(protected SessionContentService $service) {}

    /**
     * Every block belonging to the underlying lesson, alongside which of
     * them are currently assigned to this session (and their availability).
     * Blocks are never assigned automatically — this list lets a tutor see
     * the full lesson content and choose.
     */
    public function index(Request $request, SessionLesson $sessionLesson): JsonResponse
    {
        abort_unless($sessionLesson->teachingSession->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $blocks = $sessionLesson->lesson->blocks()->with('mediaItems')->get();
        $assignments = $sessionLesson->sessionLessonBlocks()->get();

        return response()->json([
            'blocks' => LessonBlockResource::collection($blocks),
            'assignments' => SessionLessonBlockResource::collection($assignments),
        ]);
    }

    /**
     * Explicitly assign a lesson block to the session with an availability strategy.
     */
    public function store(AssignSessionLessonBlockRequest $request, SessionLesson $sessionLesson): JsonResponse
    {
        $block = LessonBlock::findOrFail($request->validated('lesson_block_id'));

        $sessionLessonBlock = $this->service->assignBlock($sessionLesson, $block, $request->validated());

        return response()->json([
            'session_lesson_block' => new SessionLessonBlockResource($sessionLessonBlock),
        ], 201);
    }

    /**
     * Change an assigned block's availability strategy (including manually releasing it).
     */
    public function update(UpdateSessionLessonBlockAvailabilityRequest $request, SessionLesson $sessionLesson, SessionLessonBlock $sessionLessonBlock): JsonResponse
    {
        abort_unless($sessionLessonBlock->session_lesson_id === $sessionLesson->id, 404);

        $this->service->updateBlockAvailability($sessionLessonBlock, $request->validated());

        return response()->json([
            'session_lesson_block' => new SessionLessonBlockResource($sessionLessonBlock->fresh()),
        ]);
    }

    /**
     * Unassign a block. The underlying LessonBlock is untouched.
     */
    public function destroy(Request $request, SessionLesson $sessionLesson, SessionLessonBlock $sessionLessonBlock): JsonResponse
    {
        abort_unless($sessionLesson->teachingSession->tutor_profile_id === $request->user()->tutorProfile?->id, 403);
        abort_unless($sessionLessonBlock->session_lesson_id === $sessionLesson->id, 404);

        $this->service->removeBlock($sessionLessonBlock);

        return response()->json([
            'message' => 'Lesson block unassigned.',
        ]);
    }
}
