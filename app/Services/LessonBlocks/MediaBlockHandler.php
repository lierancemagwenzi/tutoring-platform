<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Models\LessonBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaBlockHandler implements LessonBlockHandler
{
    /**
     * Media items are managed through their own sub-resource endpoints, not
     * through this block's own fields, so there is nothing extra to validate.
     *
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array
    {
        return $existing?->content ?? [];
    }

    public function afterDelete(LessonBlock $block): void
    {
        foreach ($block->mediaItems as $mediaItem) {
            if ($mediaItem->file_path) {
                Storage::disk('public')->delete($mediaItem->file_path);
            }

            if ($mediaItem->thumbnail_path) {
                Storage::disk('public')->delete($mediaItem->thumbnail_path);
            }
        }
    }

    /**
     * Clone each media item onto the new block. File references are shared
     * (no physical file duplication needed), only the database rows are copied.
     *
     * @return array<string, mixed>
     */
    public function duplicateContent(LessonBlock $original, LessonBlock $copy): array
    {
        foreach ($original->mediaItems as $mediaItem) {
            $copy->mediaItems()->create([
                'media_type' => $mediaItem->media_type,
                'title' => $mediaItem->title,
                'description' => $mediaItem->description,
                'file_path' => $mediaItem->file_path,
                'external_url' => $mediaItem->external_url,
                'thumbnail_path' => $mediaItem->thumbnail_path,
                'original_name' => $mediaItem->original_name,
                'size' => $mediaItem->size,
                'position' => $mediaItem->position,
                'status' => $mediaItem->status,
            ]);
        }

        return [];
    }
}
