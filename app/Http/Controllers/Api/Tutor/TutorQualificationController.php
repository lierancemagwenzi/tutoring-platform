<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\TutorQualificationRequest;
use App\Http\Resources\TutorQualificationResource;
use App\Models\TutorQualification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorQualificationController extends Controller
{
    /**
     * Add a new qualification to the tutor's application.
     */
    public function store(TutorQualificationRequest $request): JsonResponse
    {
        $tutorProfile = $request->user()->tutorProfile;

        $data = $request->validated();
        $data['completion_year'] = $data['is_currently_studying'] ? null : $data['completion_year'];

        $qualification = $tutorProfile->qualifications()->create($data);

        $tutorProfile->update([
            'onboarding_step' => max($tutorProfile->onboarding_step, 4),
        ]);

        return response()->json([
            'qualification' => new TutorQualificationResource($qualification),
        ], 201);
    }

    /**
     * Update an existing qualification.
     */
    public function update(TutorQualificationRequest $request, TutorQualification $qualification): JsonResponse
    {
        $data = $request->validated();
        $data['completion_year'] = $data['is_currently_studying'] ? null : $data['completion_year'];

        $qualification->update($data);

        return response()->json([
            'qualification' => new TutorQualificationResource($qualification),
        ]);
    }

    /**
     * Delete a qualification.
     */
    public function destroy(Request $request, TutorQualification $qualification): JsonResponse
    {
        abort_unless($qualification->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $qualification->delete();

        return response()->json([
            'message' => 'Qualification deleted.',
        ]);
    }
}
