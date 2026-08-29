<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ManageSelfPacedAssessmentRequest;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedAssessmentRequest;
use App\Http\Requests\Tutor\SelfPaced\UpdateSelfPacedAssessmentRequest;
use App\Http\Resources\SelfPacedAssessmentResource;
use App\Models\SelfPacedAssessment;
use App\Models\SelfPacedModule;
use App\Services\SelfPaced\SelfPacedAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfPacedAssessmentController extends Controller
{
    public function __construct(protected SelfPacedAssessmentService $assessments) {}

    /**
     * List a module's Assessments, in order.
     */
    public function index(Request $request, SelfPacedModule $selfPacedModule): JsonResponse
    {
        abort_unless($selfPacedModule->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'assessments' => SelfPacedAssessmentResource::collection($selfPacedModule->assessments()->get()),
        ]);
    }

    /**
     * Add a new Assessment to a module. There is no limit to how many a
     * module may contain.
     */
    public function store(StoreSelfPacedAssessmentRequest $request, SelfPacedModule $selfPacedModule): JsonResponse
    {
        $assessment = $this->assessments->create($selfPacedModule, $request->validated());

        return response()->json(['assessment' => new SelfPacedAssessmentResource($assessment)], 201);
    }

    /**
     * Update an Assessment's configuration and/or provider.
     */
    public function update(UpdateSelfPacedAssessmentRequest $request, SelfPacedAssessment $selfPacedAssessment): JsonResponse
    {
        $assessment = $this->assessments->update($selfPacedAssessment, $request->validated());

        return response()->json(['assessment' => new SelfPacedAssessmentResource($assessment)]);
    }

    /**
     * Delete an Assessment.
     */
    public function destroy(ManageSelfPacedAssessmentRequest $request, SelfPacedAssessment $selfPacedAssessment): JsonResponse
    {
        $this->assessments->delete($selfPacedAssessment);

        return response()->json(['message' => 'Assessment deleted.']);
    }
}
