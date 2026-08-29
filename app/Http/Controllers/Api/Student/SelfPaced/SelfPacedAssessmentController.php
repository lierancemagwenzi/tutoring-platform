<?php

namespace App\Http\Controllers\Api\Student\SelfPaced;

use App\Enums\SelfPacedAssessmentProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\Student\SelfPaced\SelfPacedAssessmentContentResource;
use App\Models\SelfPacedAssessment;
use App\Models\SelfPacedCourse;
use App\Services\Commerce\EnrollmentService;
use App\Services\H5p\H5PService;
use App\Services\LearnerProgress\ModuleProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SelfPacedAssessmentController extends Controller
{
    /**
     * A single assessment's player-facing content (SurveyJS question set or
     * H5P content id — never correct answers).
     */
    public function show(
        Request $request,
        SelfPacedCourse $selfPacedCourse,
        SelfPacedAssessment $assessment,
        EnrollmentService $enrollments,
        ModuleProgressService $moduleProgress,
    ): JsonResponse {
        $enrollment = $enrollments->findAccessible($request->user(), $selfPacedCourse);

        abort_unless($enrollment, 403, 'You are not enrolled in this course.');
        abort_unless($assessment->module->self_paced_course_id === $selfPacedCourse->id, 404);

        try {
            $moduleProgress->assertUnlocked($enrollment, $assessment->module);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'assessment' => new SelfPacedAssessmentContentResource($assessment),
        ]);
    }

    /**
     * The H5P player model for an H5P-provider assessment, so a student can
     * actually play it — the H5P server itself has no notion of per-student
     * access, so this is gated behind the exact same enrollment/unlock
     * checks as show(). Mirrors Student\SessionContentController::h5pPlayerModel()
     * for the tutor-led side.
     */
    public function h5pPlayerModel(
        Request $request,
        SelfPacedCourse $selfPacedCourse,
        SelfPacedAssessment $assessment,
        EnrollmentService $enrollments,
        ModuleProgressService $moduleProgress,
        H5PService $h5p,
    ): JsonResponse {
        $enrollment = $enrollments->findAccessible($request->user(), $selfPacedCourse);

        abort_unless($enrollment, 403, 'You are not enrolled in this course.');
        abort_unless($assessment->module->self_paced_course_id === $selfPacedCourse->id, 404);
        abort_unless($assessment->provider === SelfPacedAssessmentProvider::H5p, 404);

        try {
            $moduleProgress->assertUnlocked($enrollment, $assessment->module);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $h5pContentId = $assessment->provider_config['h5p_content_id'] ?? null;
        abort_unless($h5pContentId, 404);

        return response()->json($h5p->playerModel($h5pContentId));
    }
}
