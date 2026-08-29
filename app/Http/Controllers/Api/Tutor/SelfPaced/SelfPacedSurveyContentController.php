<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ManageSelfPacedSurveyContentRequest;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedSurveyContentRequest;
use App\Http\Requests\Tutor\SelfPaced\UpdateSelfPacedSurveyContentRequest;
use App\Http\Resources\SelfPacedSurveyContentResource;
use App\Models\SelfPacedSurveyContent;
use App\Services\SelfPaced\SelfPacedSurveyContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfPacedSurveyContentController extends Controller
{
    public function __construct(protected SelfPacedSurveyContentService $surveyContents) {}

    /**
     * List this tutor's self-paced SurveyJS question banks — never
     * Tutor-Led Learning's Quiz content, and never another tutor's.
     * Supports optional grade_id/subject_id/curriculum_id filters for the
     * Assessment provider picker.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['grade_id', 'subject_id', 'curriculum_id']);

        return response()->json([
            'survey_contents' => SelfPacedSurveyContentResource::collection(
                $this->surveyContents->forTutor($request->user()->tutorProfile, array_filter($filters)),
            ),
        ]);
    }

    /**
     * Create a new survey question bank.
     */
    public function store(StoreSelfPacedSurveyContentRequest $request): JsonResponse
    {
        $surveyContent = $this->surveyContents->create($request->user()->tutorProfile, $request->validated());

        return response()->json([
            'survey_content' => new SelfPacedSurveyContentResource($surveyContent->load(['grade', 'subject', 'curriculum'])),
        ], 201);
    }

    /**
     * Show a survey question bank with its full question list.
     */
    public function show(Request $request, SelfPacedSurveyContent $selfPacedSurveyContent): JsonResponse
    {
        abort_unless($selfPacedSurveyContent->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'survey_content' => new SelfPacedSurveyContentResource(
                $selfPacedSurveyContent->load(['grade', 'subject', 'curriculum', 'questions']),
            ),
        ]);
    }

    /**
     * Update a survey question bank's title/description/taxonomy.
     */
    public function update(UpdateSelfPacedSurveyContentRequest $request, SelfPacedSurveyContent $selfPacedSurveyContent): JsonResponse
    {
        $surveyContent = $this->surveyContents->update($selfPacedSurveyContent, $request->validated());

        return response()->json([
            'survey_content' => new SelfPacedSurveyContentResource($surveyContent->load(['grade', 'subject', 'curriculum'])),
        ]);
    }

    /**
     * Delete a survey question bank and its questions.
     */
    public function destroy(ManageSelfPacedSurveyContentRequest $request, SelfPacedSurveyContent $selfPacedSurveyContent): JsonResponse
    {
        $this->surveyContents->delete($selfPacedSurveyContent);

        return response()->json(['message' => 'Survey content deleted.']);
    }
}
