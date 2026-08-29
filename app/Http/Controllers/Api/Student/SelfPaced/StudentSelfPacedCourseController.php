<?php

namespace App\Http\Controllers\Api\Student\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\SelfPaced\StudentSelfPacedCourseResource;
use App\Models\ActivityProgress;
use App\Models\SelfPacedAssessmentAttempt;
use App\Models\SelfPacedCourse;
use App\Services\Commerce\EnrollmentService;
use App\Services\LearnerProgress\CourseProgressService;
use App\Services\LearnerProgress\ModuleProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentSelfPacedCourseController extends Controller
{
    /**
     * The course-player bootstrap payload: full curriculum tree plus this
     * student's progress against it.
     */
    public function show(
        Request $request,
        SelfPacedCourse $selfPacedCourse,
        EnrollmentService $enrollments,
        CourseProgressService $progress,
        ModuleProgressService $moduleProgress,
    ): JsonResponse {
        $enrollment = $enrollments->findAccessible($request->user(), $selfPacedCourse);

        abort_unless($enrollment, 403, 'You are not enrolled in this course.');

        $selfPacedCourse->load(['tutorProfile', 'modules.activities', 'modules.assessments']);

        $activityIds = $selfPacedCourse->modules->flatMap(fn ($module) => $module->activities->pluck('id'));
        $assessmentIds = $selfPacedCourse->modules->flatMap(fn ($module) => $module->assessments->pluck('id'));

        $completedActivityIds = ActivityProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('self_paced_activity_id', $activityIds)
            ->whereNotNull('completed_at')
            ->pluck('self_paced_activity_id');

        $passedAssessmentIds = SelfPacedAssessmentAttempt::query()
            ->where('student_id', $enrollment->student_id)
            ->whereIn('self_paced_assessment_id', $assessmentIds)
            ->where('passed', true)
            ->pluck('self_paced_assessment_id');

        return response()->json([
            'course' => new StudentSelfPacedCourseResource(
                $selfPacedCourse,
                $enrollment,
                $progress->summarize($enrollment),
                $moduleProgress->statesFor($enrollment, $selfPacedCourse->modules),
                $completedActivityIds,
                $passedAssessmentIds,
            ),
        ]);
    }
}
