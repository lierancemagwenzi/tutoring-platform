<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\GradeSubmissionRequest;
use App\Http\Requests\Tutor\PublishSubmissionRequest;
use App\Http\Requests\Tutor\ReturnSubmissionRequest;
use App\Http\Requests\Tutor\ReviewSubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\SessionLessonBlock;
use App\Models\Submission;
use App\Services\Submissions\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(protected SubmissionService $service) {}

    /**
     * Every student's submission attempts against this delivery instance.
     */
    public function index(Request $request, SessionLessonBlock $sessionLessonBlock): JsonResponse
    {
        abort_unless(
            $sessionLessonBlock->sessionLesson->teachingSession->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        $submissions = $sessionLessonBlock->submissions()
            ->whereIn('status', ['submitted', 'under_review', 'returned', 'graded'])
            ->with(['sessionLessonBlock.lessonBlock', 'student', 'studentAttachments', 'feedbackAttachments'])
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json([
            'submissions' => SubmissionResource::collection($submissions),
        ]);
    }

    /**
     * A single submission, with its full attempt detail.
     */
    public function show(Request $request, Submission $submission): JsonResponse
    {
        abort_unless(
            $submission->sessionLessonBlock->sessionLesson->teachingSession->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        return response()->json([
            'submission' => new SubmissionResource(
                $submission->load(['sessionLessonBlock.lessonBlock', 'student', 'studentAttachments', 'feedbackAttachments']),
            ),
        ]);
    }

    /**
     * Mark a submission as being actively reviewed.
     */
    public function review(ReviewSubmissionRequest $request, Submission $submission): JsonResponse
    {
        $this->service->markUnderReview($submission);

        return response()->json([
            'submission' => new SubmissionResource(
                $submission->fresh(['sessionLessonBlock.lessonBlock', 'student', 'studentAttachments', 'feedbackAttachments']),
            ),
        ]);
    }

    /**
     * Send a submission back to the student for revision.
     */
    public function returnForRevision(ReturnSubmissionRequest $request, Submission $submission): JsonResponse
    {
        $this->service->returnForRevision($submission);

        return response()->json([
            'submission' => new SubmissionResource(
                $submission->fresh(['sessionLessonBlock.lessonBlock', 'student', 'studentAttachments', 'feedbackAttachments']),
            ),
        ]);
    }

    /**
     * Grade a submission. Pass/fail is derived from the Session Lesson
     * Block's own Passing Score.
     */
    public function grade(GradeSubmissionRequest $request, Submission $submission): JsonResponse
    {
        $this->service->grade(
            $submission,
            (float) $request->validated('score'),
            $request->filled('feedback_text_html') || $request->filled('feedback_text_json')
                ? ['html' => $request->validated('feedback_text_html'), 'json' => $request->validated('feedback_text_json')]
                : null,
            $request->user(),
        );

        return response()->json([
            'submission' => new SubmissionResource(
                $submission->fresh(['sessionLessonBlock.lessonBlock', 'student', 'studentAttachments', 'feedbackAttachments']),
            ),
        ]);
    }

    /**
     * Reveal a graded submission's score/feedback to the student.
     */
    public function publish(PublishSubmissionRequest $request, Submission $submission): JsonResponse
    {
        $this->service->publish($submission);

        return response()->json([
            'submission' => new SubmissionResource(
                $submission->fresh(['sessionLessonBlock.lessonBlock', 'student', 'studentAttachments', 'feedbackAttachments']),
            ),
        ]);
    }
}
