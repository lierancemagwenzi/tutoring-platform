<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderQuizQuestionsRequest;
use App\Http\Requests\Tutor\StoreQuizQuestionRequest;
use App\Http\Requests\Tutor\UpdateQuizQuestionRequest;
use App\Http\Resources\QuizQuestionResource;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizQuestionController extends Controller
{
    /**
     * Add a new question to a quiz.
     */
    public function store(StoreQuizQuestionRequest $request, Quiz $quiz): JsonResponse
    {
        $question = $quiz->questions()->create([
            'position' => $quiz->questions()->count(),
            'type' => $request->validated('type'),
            'definition' => $this->buildDefinition($request),
            'points' => $request->validated('points'),
        ]);

        return response()->json([
            'question' => new QuizQuestionResource($question),
        ], 201);
    }

    /**
     * Update an existing question.
     */
    public function update(UpdateQuizQuestionRequest $request, QuizQuestion $question): JsonResponse
    {
        $question->update([
            'type' => $request->validated('type'),
            'definition' => $this->buildDefinition($request),
            'points' => $request->validated('points'),
        ]);

        return response()->json([
            'question' => new QuizQuestionResource($question),
        ]);
    }

    /**
     * Delete a question.
     */
    public function destroy(Request $request, QuizQuestion $question): JsonResponse
    {
        abort_unless(
            $question->quiz->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        $question->delete();

        return response()->json([
            'message' => 'Question deleted.',
        ]);
    }

    /**
     * Persist the drag-and-drop order of a quiz's questions.
     */
    public function reorder(ReorderQuizQuestionsRequest $request, Quiz $quiz): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('question_ids') as $position => $questionId) {
                QuizQuestion::whereKey($questionId)->update(['position' => $position]);
            }
        });

        return response()->json([
            'questions' => QuizQuestionResource::collection($quiz->questions()->get()),
        ]);
    }

    /**
     * Assemble the SurveyJS-compatible question definition from the flattened request fields.
     *
     * @return array<string, mixed>
     */
    private function buildDefinition(Request $request): array
    {
        return array_filter([
            'title' => $request->validated('text'),
            'choices' => $request->validated('choices'),
            'correctAnswer' => $request->validated('correct_answer'),
        ], fn ($value) => $value !== null);
    }
}
