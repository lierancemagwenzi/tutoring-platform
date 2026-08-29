<?php

namespace App\Http\Controllers\Api\Student\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SelfPaced\CompleteActivityRequest;
use App\Http\Resources\Student\SelfPaced\SelfPacedActivityContentResource;
use App\Models\ActivityProgress;
use App\Models\SelfPacedActivity;
use App\Models\SelfPacedCourse;
use App\Services\Commerce\EnrollmentService;
use App\Services\LearnerProgress\ActivityProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SelfPacedActivityController extends Controller
{
    /**
     * A single activity's content, for the course player. Records that the
     * student has viewed it (for "last activity"/analytics purposes).
     */
    public function show(
        Request $request,
        SelfPacedCourse $selfPacedCourse,
        SelfPacedActivity $activity,
        EnrollmentService $enrollments,
        ActivityProgressService $activityProgress,
    ): JsonResponse {
        $enrollment = $enrollments->findAccessible($request->user(), $selfPacedCourse);

        abort_unless($enrollment, 403, 'You are not enrolled in this course.');
        abort_unless($activity->module->self_paced_course_id === $selfPacedCourse->id, 404);

        try {
            $activityProgress->recordViewed($enrollment, $activity);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $activity->load('attachments');

        $completed = ActivityProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('self_paced_activity_id', $activity->id)
            ->whereNotNull('completed_at')
            ->exists();
        $activity->setAttribute('completed', $completed);

        return response()->json([
            'activity' => new SelfPacedActivityContentResource($activity),
        ]);
    }

    /**
     * Mark an activity complete — either an explicit "Mark Complete" click
     * or the tutor-configured auto-complete-after-viewing path; the
     * frontend decides which to call this for, the backend doesn't care.
     */
    public function complete(
        CompleteActivityRequest $request,
        SelfPacedCourse $selfPacedCourse,
        SelfPacedActivity $activity,
        EnrollmentService $enrollments,
        ActivityProgressService $activityProgress,
    ): JsonResponse {
        $enrollment = $enrollments->findAccessible($request->user(), $selfPacedCourse);

        abort_unless($enrollment, 403, 'You are not enrolled in this course.');
        abort_unless($activity->module->self_paced_course_id === $selfPacedCourse->id, 404);

        try {
            $activityProgress->markComplete($enrollment, $activity, $request->validated('time_spent_seconds'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Activity marked complete.']);
    }
}
