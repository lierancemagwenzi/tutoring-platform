<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\LessonBlockType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Student\LessonBlockResource;
use App\Models\Booking;
use App\Models\LessonBlock;
use App\Models\SessionLesson;
use App\Models\SessionLessonBlock;
use App\Services\H5p\H5PService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionContentController extends Controller
{
    public function __construct(protected H5PService $h5p) {}

    /**
     * The lessons assigned across all of this booking's scheduled sessions,
     * each exposing only the lesson blocks that are currently available —
     * never unassigned, future-scheduled, manually-hidden, or expired
     * blocks. Ordered by session date/time first, then position within
     * each session, so a multi-session package reads chronologically.
     */
    public function index(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->student_id === $request->user()->id, 403);

        $teachingSessionIds = $booking->teachingSessions()->pluck('teaching_sessions.id');

        if ($teachingSessionIds->isEmpty()) {
            return response()->json(['lessons' => []]);
        }

        $sessionLessons = SessionLesson::whereIn('teaching_session_id', $teachingSessionIds)
            ->with(['lesson', 'teachingSession', 'sessionLessonBlocks.lessonBlock.mediaItems'])
            ->get()
            ->sortBy(fn (SessionLesson $sessionLesson) => [
                $sessionLesson->teachingSession->date->format('Y-m-d'),
                $sessionLesson->teachingSession->start_time,
                $sessionLesson->position,
            ])
            ->values();

        $lessons = $sessionLessons->map(fn (SessionLesson $sessionLesson) => [
            'id' => $sessionLesson->id,
            'lesson' => [
                'id' => $sessionLesson->lesson->id,
                'title' => $sessionLesson->lesson->title,
                'description' => $sessionLesson->lesson->description,
                'estimated_duration_minutes' => $sessionLesson->lesson->estimated_duration_minutes,
            ],
            'position' => $sessionLesson->position,
            'blocks' => LessonBlockResource::collection(
                $sessionLesson->sessionLessonBlocks
                    ->filter(fn ($sessionLessonBlock) => $sessionLessonBlock->isAvailable())
                    ->map(fn ($sessionLessonBlock) => $sessionLessonBlock->lessonBlock)
                    ->values()
            ),
        ]);

        return response()->json(['lessons' => $lessons]);
    }

    /**
     * A single available lesson block, for the dedicated content screen a
     * student lands on after tapping it in the lesson listing.
     */
    public function showBlock(Request $request, Booking $booking, LessonBlock $lessonBlock): JsonResponse
    {
        abort_unless($booking->student_id === $request->user()->id, 403);
        abort_unless($this->availableSessionLessonBlock($booking, $lessonBlock), 403);

        return response()->json([
            'block' => new LessonBlockResource($lessonBlock->load('mediaItems')),
        ]);
    }

    /**
     * The H5P player model for a single H5P lesson block, so a student can
     * actually play it — gated behind the exact same availability check
     * that decides whether the block appears in index() at all, since the
     * H5P server itself has no notion of per-student access.
     */
    public function h5pPlayerModel(Request $request, Booking $booking, LessonBlock $lessonBlock): JsonResponse
    {
        abort_unless($booking->student_id === $request->user()->id, 403);
        abort_unless($lessonBlock->block_type === LessonBlockType::H5p, 404);
        abort_unless($this->availableSessionLessonBlock($booking, $lessonBlock), 403);

        $h5pContentId = $lessonBlock->content['h5p_content_id'] ?? null;
        abort_unless($h5pContentId, 404);

        return response()->json($this->h5p->playerModel($h5pContentId));
    }

    /**
     * The session-lesson-block assignment that makes this lesson block
     * available to this booking's student right now, if one exists.
     */
    private function availableSessionLessonBlock(Booking $booking, LessonBlock $lessonBlock): ?SessionLessonBlock
    {
        $sessionLessonBlock = $booking->sessionLessonBlockFor($lessonBlock);

        return $sessionLessonBlock && $sessionLessonBlock->isAvailable() ? $sessionLessonBlock : null;
    }
}
