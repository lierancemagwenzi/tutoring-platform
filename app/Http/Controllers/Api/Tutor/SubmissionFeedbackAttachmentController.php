<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\MediaType;
use App\Enums\SubmissionAttachmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\StoreSubmissionFeedbackAttachmentRequest;
use App\Http\Resources\SubmissionAttachmentResource;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use App\Services\Submissions\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionFeedbackAttachmentController extends Controller
{
    public function __construct(protected SubmissionService $service) {}

    /**
     * Attach a feedback file to a submission (e.g. an annotated PDF or solution sheet).
     */
    public function store(StoreSubmissionFeedbackAttachmentRequest $request, Submission $submission): JsonResponse
    {
        $attachment = $this->service->addAttachment(
            $submission,
            SubmissionAttachmentCategory::Feedback,
            MediaType::from($request->validated('media_type')),
            $request->file('file'),
            $request->validated('title'),
        );

        return response()->json([
            'attachment' => new SubmissionAttachmentResource($attachment),
        ], 201);
    }

    /**
     * Remove a feedback attachment.
     */
    public function destroy(Request $request, SubmissionAttachment $submissionAttachment): JsonResponse
    {
        $submission = $submissionAttachment->submission;

        abort_unless(
            $submission->sessionLessonBlock->sessionLesson->teachingSession->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );
        abort_unless($submissionAttachment->category === SubmissionAttachmentCategory::Feedback, 403);

        $this->service->removeAttachment($submissionAttachment);

        return response()->json([
            'message' => 'Attachment removed.',
        ]);
    }
}
