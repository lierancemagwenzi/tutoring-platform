<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmitTutorApplicationController extends Controller
{
    /**
     * Submit the tutor's application for review.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $tutorProfile = $user->tutorProfile()->with(['qualifications', 'documents'])->firstOrFail();

        $missing = $tutorProfile->missingSubmissionRequirements();

        if ($missing !== []) {
            return response()->json([
                'message' => 'Your application is incomplete.',
                'errors' => ['application' => $missing],
            ], 422);
        }

        $tutorProfile->update([
            'onboarding_complete' => true,
            'onboarding_step' => 6,
        ]);

        $user->update(['status' => UserStatus::Pending]);

        return response()->json([
            'message' => 'Your tutor application has been submitted successfully.',
        ]);
    }
}
