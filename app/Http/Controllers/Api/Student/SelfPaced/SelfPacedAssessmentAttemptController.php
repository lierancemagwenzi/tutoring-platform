<?php

namespace App\Http\Controllers\Api\Student\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SelfPaced\CompleteAssessmentAttemptRequest;
use App\Http\Requests\Student\SelfPaced\MarkAssessmentAttemptInProgressRequest;
use App\Http\Resources\Student\SelfPaced\SelfPacedAssessmentAttemptResource;
use App\Models\SelfPacedAssessment;
use App\Models\SelfPacedAssessmentAttempt;
use App\Models\SelfPacedCourse;
use App\Services\Commerce\EnrollmentService;
use App\Services\LearnerProgress\SelfPacedAssessmentAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SelfPacedAssessmentAttemptController extends Controller
{
    /**
     * Launch an assessment: starts a new attempt, or resumes the currently open one.
     */
    public function store(
        Request $request,
        SelfPacedCourse $selfPacedCourse,
        SelfPacedAssessment $assessment,
        EnrollmentService $enrollments,
        SelfPacedAssessmentAttemptService $service,
    ): JsonResponse {
        $enrollment = $enrollments->findAccessible($request->user(), $selfPacedCourse);

        abort_unless($enrollment, 403, 'You are not enrolled in this course.');
        abort_unless($assessment->module->self_paced_course_id === $selfPacedCourse->id, 404);

        try {
            $attempt = $service->start($assessment, $enrollment);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'attempt' => new SelfPacedAssessmentAttemptResource($attempt),
        ], 201);
    }

    /**
     * Mark an attempt as actively under way, once the provider's player has initialized.
     */
    public function markInProgress(
        MarkAssessmentAttemptInProgressRequest $request,
        SelfPacedCourse $selfPacedCourse,
        SelfPacedAssessment $assessment,
        SelfPacedAssessmentAttempt $attempt,
        SelfPacedAssessmentAttemptService $service,
    ): JsonResponse {
        $service->markInProgress($attempt);

        return response()->json([
            'attempt' => new SelfPacedAssessmentAttemptResource($attempt->fresh()),
        ]);
    }

    /**
     * Complete an attempt with the provider's raw result, scoring it
     * immediately and re-evaluating chapter/course completion.
     */
    public function complete(
        CompleteAssessmentAttemptRequest $request,
        SelfPacedCourse $selfPacedCourse,
        SelfPacedAssessment $assessment,
        SelfPacedAssessmentAttempt $attempt,
        EnrollmentService $enrollments,
        SelfPacedAssessmentAttemptService $service,
    ): JsonResponse {
        $enrollment = $enrollments->findAccessible($request->user(), $selfPacedCourse);

        abort_unless($enrollment, 403, 'You are not enrolled in this course.');

        $service->complete($attempt, $request->validated('raw_result'), $enrollment);

        return response()->json([
            'attempt' => new SelfPacedAssessmentAttemptResource($attempt->fresh()),
        ]);
    }
}
