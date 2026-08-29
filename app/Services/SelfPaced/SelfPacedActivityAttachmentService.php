<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedActivity;
use App\Models\SelfPacedActivityAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SelfPacedActivityAttachmentService
{
    public function store(SelfPacedActivity $activity, array $data): SelfPacedActivityAttachment
    {
        $attributes = [
            'media_type' => $data['media_type'],
            'title' => $data['title'] ?? null,
            'position' => $activity->attachments()->count(),
        ];

        if (isset($data['file']) && $data['file'] instanceof UploadedFile) {
            $file = $data['file'];
            $attributes['file_path'] = $file->store('self-paced-activity-attachments', 'public');
            $attributes['original_name'] = $file->getClientOriginalName();
            $attributes['size'] = $file->getSize();
        } elseif (isset($data['external_url'])) {
            $attributes['external_url'] = $data['external_url'];
        }

        return $activity->attachments()->create($attributes)->fresh();
    }

    public function update(SelfPacedActivityAttachment $attachment, array $data): SelfPacedActivityAttachment
    {
        $attributes = [];

        if (isset($data['title'])) {
            $attributes['title'] = $data['title'];
        }

        if (isset($data['external_url'])) {
            $attributes['external_url'] = $data['external_url'];
        }

        if (isset($data['file']) && $data['file'] instanceof UploadedFile) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $file = $data['file'];
            $attributes['file_path'] = $file->store('self-paced-activity-attachments', 'public');
            $attributes['original_name'] = $file->getClientOriginalName();
            $attributes['size'] = $file->getSize();
        }

        $attachment->update($attributes);

        return $attachment->fresh();
    }

    public function delete(SelfPacedActivityAttachment $attachment): void
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();
    }

    /**
     * @param  list<int>  $attachmentIds
     */
    public function reorder(SelfPacedActivity $activity, array $attachmentIds): void
    {
        foreach ($attachmentIds as $position => $attachmentId) {
            SelfPacedActivityAttachment::whereKey($attachmentId)->update(['position' => $position]);
        }
    }
}
