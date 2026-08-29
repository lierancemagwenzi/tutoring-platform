<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\BasicInformationRequest;
use App\Http\Resources\TutorApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class BasicInformationController extends Controller
{
    /**
     * Save step 1 (basic information) of the tutor application.
     */
    public function __invoke(BasicInformationRequest $request): JsonResponse
    {
        $tutorProfile = $request->user()->tutorProfile;
        $data = $request->safe()->except('profile_photo');

        if ($request->hasFile('profile_photo')) {
            if ($tutorProfile->profile_photo) {
                Storage::disk('public')->delete($tutorProfile->profile_photo);
            }

            $data['profile_photo'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $data['onboarding_step'] = max($tutorProfile->onboarding_step, 2);

        $tutorProfile->update($data);

        return response()->json([
            'application' => new TutorApplicationResource($tutorProfile->fresh(['qualifications', 'documents'])),
        ]);
    }
}
