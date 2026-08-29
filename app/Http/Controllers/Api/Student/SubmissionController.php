<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreSubmissionRequest;
use App\Http\Requests\Student\SubmitSubmissionRequest;
use App\Http\Requests\Student\UpdateSubmissionRequest;
use App\Http\Resources\Student\SubmissionResource;
use App\Models\Booking;
use App\Models\LessonBlock;
use App\Models\Submission;
use App\Services\Submissions\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(protected SubmissionService $service) {}

    /**
     * The logged in student's own attempt history for this delivery instance.
     */
    public function index(Request $request, Booking $booking, LessonBlock $lessonBlock): JsonResponse
    {
        abort_unless($booking->student_id === $request->user()->id, 403);

        $sessionLessonBlock = $booking->sessionLessonBlockFor($lessonBlock);
        abort_unless($sessionLessonBlock, 404);

        $submissions = $sessionLessonBlock->submissions()
            ->where('student_id', $request->user()->id)
            ->with(['sessionLessonBlock.lessonBlock', 'studentAttachments', 'feedbackAttachments'])
            ->orderByDesc('attempt_number')
            ->get();

        return response()->json([
            'submissions' => SubmissionResource::collection($submissions),
        ]);
    }

    /**
     * Start a new attempt, or resume the currently open draft.
     */
    public function store(StoreSubmissionRequest $request, Booking $booking, LessonBlock $lessonBlock): JsonResponse
    {
        $sessionLessonBlock = $booking->sessionLessonBlockFor($lessonBlock);

        $submission = $this->service->startOrResumeDraft($sessionLessonBlock, $request->user());

        return response()->json([
            'submission' => new SubmissionResource($submission->load(['sessionLessonBlock.lessonBlock', 'studentAttachments', 'feedbackAttachments'])),
        ], 201);
    }

    /**
     * Update a draft's submission text.
     */
    public function update(UpdateSubmissionRequest $request, Submission $submission): JsonResponse
    {
        $this->service->updateDraft($submission, $request->validated());

        return response()->json([
            'submission' => new SubmissionResource($submission->fresh(['sessionLessonBlock.lessonBlock', 'studentAttachments', 'feedbackAttachments'])),
        ]);
    }

    /**
     * Submit a draft, locking it against further edits.
     */
    public function submit(SubmitSubmissionRequest $request, Submission $submission): JsonResponse
    {
        $this->service->submit($submission);

        return response()->json([
            'submission' => new SubmissionResource($submission->fresh(['sessionLessonBlock.lessonBlock', 'studentAttachments', 'feedbackAttachments'])),
        ]);
    }
}
