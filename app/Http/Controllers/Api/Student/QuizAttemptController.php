<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\QuizAttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StartQuizAttemptRequest;
use App\Http\Requests\Student\SubmitQuizAttemptRequest;
use App\Http\Resources\Student\QuizAttemptResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Quizzes\QuizScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    /**
     * Return the logged in student's past attempts at a quiz.
     */
    public function index(Request $request, Quiz $quiz): JsonResponse
    {
        $attempts = $quiz->attempts()
            ->where('student_id', $request->user()->id)
            ->latest('attempt_number')
            ->get();

        return response()->json([
            'attempts' => QuizAttemptResource::collection($attempts),
        ]);
    }

    /**
     * Start a new attempt at a quiz.
     */
    public function store(StartQuizAttemptRequest $request, Quiz $quiz): JsonResponse
    {
        $attemptNumber = $quiz->attempts()->where('student_id', $request->user()->id)->count() + 1;

        $attempt = $quiz->attempts()->create([
            'student_id' => $request->user()->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'status' => QuizAttemptStatus::InProgress->value,
        ]);

        return response()->json([
            'attempt' => new QuizAttemptResource($attempt),
        ], 201);
    }

    /**
     * Submit answers for an in-progress attempt and score it.
     */
    public function submit(SubmitQuizAttemptRequest $request, QuizAttempt $attempt, QuizScoringService $scoringService): JsonResponse
    {
        $scoringService->score($attempt, $request->validated('answers'));

        $attempt->update([
            'completed_at' => now(),
            'duration_seconds' => (int) round($attempt->started_at->diffInSeconds(now(), absolute: true)),
            'status' => QuizAttemptStatus::Completed->value,
        ]);

        return response()->json([
            'attempt' => new QuizAttemptResource($attempt->fresh()->load('answers')),
        ]);
    }
}
