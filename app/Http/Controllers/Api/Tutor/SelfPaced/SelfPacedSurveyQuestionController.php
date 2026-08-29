<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ManageSelfPacedSurveyQuestionRequest;
use App\Http\Requests\Tutor\SelfPaced\ReorderSelfPacedSurveyQuestionsRequest;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedSurveyQuestionRequest;
use App\Http\Requests\Tutor\SelfPaced\UpdateSelfPacedSurveyQuestionRequest;
use App\Http\Resources\SelfPacedSurveyQuestionResource;
use App\Models\SelfPacedSurveyContent;
use App\Models\SelfPacedSurveyQuestion;
use App\Services\SelfPaced\SelfPacedSurveyQuestionService;
use Illuminate\Http\JsonResponse;

class SelfPacedSurveyQuestionController extends Controller
{
    public function __construct(protected SelfPacedSurveyQuestionService $questions) {}

    /**
     * Add a new question to a survey content.
     */
    public function store(StoreSelfPacedSurveyQuestionRequest $request, SelfPacedSurveyContent $selfPacedSurveyContent): JsonResponse
    {
        $question = $this->questions->create($selfPacedSurveyContent, $request->validated());

        return response()->json(['question' => new SelfPacedSurveyQuestionResource($question)], 201);
    }

    /**
     * Update an existing question.
     */
    public function update(UpdateSelfPacedSurveyQuestionRequest $request, SelfPacedSurveyQuestion $selfPacedSurveyQuestion): JsonResponse
    {
        $question = $this->questions->update($selfPacedSurveyQuestion, $request->validated());

        return response()->json(['question' => new SelfPacedSurveyQuestionResource($question)]);
    }

    /**
     * Delete a question.
     */
    public function destroy(ManageSelfPacedSurveyQuestionRequest $request, SelfPacedSurveyQuestion $selfPacedSurveyQuestion): JsonResponse
    {
        $this->questions->delete($selfPacedSurveyQuestion);

        return response()->json(['message' => 'Question deleted.']);
    }

    /**
     * Persist the drag-and-drop order of a survey's questions.
     */
    public function reorder(ReorderSelfPacedSurveyQuestionsRequest $request, SelfPacedSurveyContent $selfPacedSurveyContent): JsonResponse
    {
        $this->questions->reorder($request->validated('question_ids'));

        return response()->json([
            'questions' => SelfPacedSurveyQuestionResource::collection($selfPacedSurveyContent->questions()->get()),
        ]);
    }
}
