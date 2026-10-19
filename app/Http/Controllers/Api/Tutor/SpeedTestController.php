<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\StoreSpeedTestResultRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\TutorSpeedTestEligibility;
use Illuminate\Http\JsonResponse;

class SpeedTestController extends Controller
{
    /**
     * Show the connection minimums a tutor applicant has to meet.
     */
    public function show(TutorSpeedTestEligibility $eligibility): JsonResponse
    {
        return response()->json(['requirements' => $eligibility->requirements()]);
    }

    /**
     * Record a registration speed test result and say whether it meets the
     * minimums. Once a tutor has passed they stay passed — a later slow
     * test (e.g. on a different network) doesn't lock them out again.
     */
    public function store(StoreSpeedTestResultRequest $request, TutorSpeedTestEligibility $eligibility): JsonResponse
    {
        $metrics = array_map(fn ($value) => $value === null ? null : (float) $value, $request->validated());
        $failures = $eligibility->failures($metrics);
        $tutorProfile = $request->user()->tutorProfile;

        $tutorProfile->speed_test_result = [...$metrics, 'passed' => $failures === [], 'tested_at' => now()->toIso8601String()];

        if ($failures === [] && ! $tutorProfile->speed_test_passed_at) {
            $tutorProfile->speed_test_passed_at = now();
        }

        $tutorProfile->save();

        return response()->json([
            'passed' => $failures === [],
            'failures' => $failures,
            'requirements' => $eligibility->requirements(),
            'user' => new UserResource($request->user()->fresh(['tutorProfile', 'studentGuardian'])),
        ]);
    }
}
