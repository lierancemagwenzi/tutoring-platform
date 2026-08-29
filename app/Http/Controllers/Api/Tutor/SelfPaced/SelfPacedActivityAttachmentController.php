<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ManageSelfPacedActivityAttachmentRequest;
use App\Http\Requests\Tutor\SelfPaced\ReorderSelfPacedActivityAttachmentsRequest;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedActivityAttachmentRequest;
use App\Http\Requests\Tutor\SelfPaced\UpdateSelfPacedActivityAttachmentRequest;
use App\Http\Resources\SelfPacedActivityAttachmentResource;
use App\Models\SelfPacedActivity;
use App\Models\SelfPacedActivityAttachment;
use App\Services\SelfPaced\SelfPacedActivityAttachmentService;
use Illuminate\Http\JsonResponse;

class SelfPacedActivityAttachmentController extends Controller
{
    public function __construct(protected SelfPacedActivityAttachmentService $attachments) {}

    /**
     * Add a new attachment (uploaded file or external URL) to an activity.
     */
    public function store(StoreSelfPacedActivityAttachmentRequest $request, SelfPacedActivity $selfPacedActivity): JsonResponse
    {
        $attachment = $this->attachments->store($selfPacedActivity, $request->validated() + ['file' => $request->file('file')]);

        return response()->json(['attachment' => new SelfPacedActivityAttachmentResource($attachment)], 201);
    }

    /**
     * Update an existing attachment.
     */
    public function update(UpdateSelfPacedActivityAttachmentRequest $request, SelfPacedActivityAttachment $selfPacedActivityAttachment): JsonResponse
    {
        $attachment = $this->attachments->update($selfPacedActivityAttachment, $request->validated() + ['file' => $request->file('file')]);

        return response()->json(['attachment' => new SelfPacedActivityAttachmentResource($attachment)]);
    }

    /**
     * Remove an attachment.
     */
    public function destroy(ManageSelfPacedActivityAttachmentRequest $request, SelfPacedActivityAttachment $selfPacedActivityAttachment): JsonResponse
    {
        $this->attachments->delete($selfPacedActivityAttachment);

        return response()->json(['message' => 'Attachment deleted.']);
    }

    /**
     * Persist the drag-and-drop order of an activity's attachments.
     */
    public function reorder(ReorderSelfPacedActivityAttachmentsRequest $request, SelfPacedActivity $selfPacedActivity): JsonResponse
    {
        $this->attachments->reorder($selfPacedActivity, $request->validated('attachment_ids'));

        return response()->json([
            'attachments' => SelfPacedActivityAttachmentResource::collection($selfPacedActivity->attachments()->get()),
        ]);
    }
}
