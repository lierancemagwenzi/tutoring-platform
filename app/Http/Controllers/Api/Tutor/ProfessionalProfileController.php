<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ProfessionalProfileRequest;
use App\Http\Resources\TutorApplicationResource;
use Illuminate\Http\JsonResponse;

class ProfessionalProfileController extends Controller
{
    /**
     * Save step 2 (professional profile) of the tutor application.
     */
    public function __invoke(ProfessionalProfileRequest $request): JsonResponse
    {
        $tutorProfile = $request->user()->tutorProfile;

        $tutorProfile->update([
            ...$request->validated(),
            'onboarding_step' => max($tutorProfile->onboarding_step, 3),
        ]);

        return response()->json([
            'application' => new TutorApplicationResource($tutorProfile->fresh(['qualifications', 'documents'])),
        ]);
    }
}
