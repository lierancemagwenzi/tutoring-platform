<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\LessonBlockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\PublishQuizRequest;
use App\Http\Requests\Tutor\UpdateQuizRequest;
use App\Http\Resources\QuizResource;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /**
     * Show a single quiz belonging to the logged in tutor.
     */
    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        abort_unless($quiz->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'quiz' => new QuizResource($quiz->load('questions')),
        ]);
    }

    /**
     * Update a quiz's title, description, and settings.
     */
    public function update(UpdateQuizRequest $request, Quiz $quiz): JsonResponse
    {
        $quiz->update($request->validated());

        return response()->json([
            'quiz' => new QuizResource($quiz->load('questions')),
        ]);
    }

    /**
     * Publish a quiz, making it available for students to attempt. Also
     * publishes the lesson block that wraps it — the tutor has no separate
     * control for that block's own status, so leaving it Draft here would
     * both mislead the Lesson Builder's badge and block the quiz from being
     * assignable to a session (see AssignSessionLessonBlockRequest).
     */
    public function publish(PublishQuizRequest $request, Quiz $quiz): JsonResponse
    {
        $quiz->update(['status' => LessonBlockStatus::Published]);
        $quiz->lessonBlock()?->update(['status' => LessonBlockStatus::Published]);

        return response()->json([
            'quiz' => new QuizResource($quiz->load('questions')),
        ]);
    }

    /**
     * Delete a quiz.
     */
    public function destroy(Request $request, Quiz $quiz): JsonResponse
    {
        abort_unless($quiz->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz deleted.',
        ]);
    }
}
