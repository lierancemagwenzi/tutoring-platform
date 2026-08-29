<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\LessonBlockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderActivityAttachmentsRequest;
use App\Http\Requests\Tutor\StoreActivityAttachmentRequest;
use App\Http\Requests\Tutor\UpdateActivityAttachmentRequest;
use App\Http\Resources\ActivityAttachmentResource;
use App\Models\ActivityAttachment;
use App\Models\LearningActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActivityAttachmentController extends Controller
{
    /**
     * Return the attachments belonging to a learning activity, in position order.
     */
    public function index(Request $request, LearningActivity $learningActivity): JsonResponse
    {
        abort_unless(
            $learningActivity->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        return response()->json([
            'attachments' => ActivityAttachmentResource::collection($learningActivity->attachments),
        ]);
    }

    /**
     * Add a new attachment to a learning activity.
     */
    public function store(StoreActivityAttachmentRequest $request, LearningActivity $learningActivity): JsonResponse
    {
        $file = $request->file('file');

        $attachment = $learningActivity->attachments()->create([
            'media_type' => $request->validated('media_type'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'file_path' => $file->store('activity-attachments', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'position' => $learningActivity->attachments()->count(),
            'status' => $request->validated('status') ?? LessonBlockStatus::Draft->value,
        ]);

        return response()->json([
            'attachment' => new ActivityAttachmentResource($attachment),
        ], 201);
    }

    /**
     * Update an existing attachment.
     */
    public function update(UpdateActivityAttachmentRequest $request, ActivityAttachment $activityAttachment): JsonResponse
    {
        $attributes = [
            'media_type' => $request->validated('media_type'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
        ];

        if ($request->hasFile('file')) {
            if ($activityAttachment->file_path) {
                Storage::disk('public')->delete($activityAttachment->file_path);
            }

            $file = $request->file('file');
            $attributes['file_path'] = $file->store('activity-attachments', 'public');
            $attributes['original_name'] = $file->getClientOriginalName();
            $attributes['size'] = $file->getSize();
        }

        $activityAttachment->update($attributes);

        return response()->json([
            'attachment' => new ActivityAttachmentResource($activityAttachment),
        ]);
    }

    /**
     * Remove an attachment.
     */
    public function destroy(Request $request, ActivityAttachment $activityAttachment): JsonResponse
    {
        abort_unless(
            $activityAttachment->learningActivity->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        if ($activityAttachment->file_path) {
            Storage::disk('public')->delete($activityAttachment->file_path);
        }

        $activityAttachment->delete();

        return response()->json([
            'message' => 'Attachment deleted.',
        ]);
    }

    /**
     * Persist the drag-and-drop order of a learning activity's attachments.
     */
    public function reorder(ReorderActivityAttachmentsRequest $request, LearningActivity $learningActivity): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('attachment_ids') as $position => $attachmentId) {
                ActivityAttachment::whereKey($attachmentId)->update(['position' => $position]);
            }
        });

        return response()->json([
            'attachments' => ActivityAttachmentResource::collection($learningActivity->attachments()->get()),
        ]);
    }
}
