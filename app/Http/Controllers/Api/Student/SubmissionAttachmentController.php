<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\MediaType;
use App\Enums\SubmissionAttachmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreSubmissionAttachmentRequest;
use App\Http\Resources\SubmissionAttachmentResource;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use App\Services\Submissions\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionAttachmentController extends Controller
{
    public function __construct(protected SubmissionService $service) {}

    /**
     * Attach a file to a draft submission.
     */
    public function store(StoreSubmissionAttachmentRequest $request, Submission $submission): JsonResponse
    {
        $attachment = $this->service->addAttachment(
            $submission,
            SubmissionAttachmentCategory::Student,
            MediaType::from($request->validated('media_type')),
            $request->file('file'),
            $request->validated('title'),
        );

        return response()->json([
            'attachment' => new SubmissionAttachmentResource($attachment),
        ], 201);
    }

    /**
     * Remove an attachment from a still-editable draft.
     */
    public function destroy(Request $request, SubmissionAttachment $submissionAttachment): JsonResponse
    {
        $submission = $submissionAttachment->submission;

        abort_unless($submission->student_id === $request->user()->id, 403);
        abort_unless($submission->isEditable(), 403);
        abort_unless($submissionAttachment->category === SubmissionAttachmentCategory::Student, 403);

        $this->service->removeAttachment($submissionAttachment);

        return response()->json([
            'message' => 'Attachment removed.',
        ]);
    }
}
