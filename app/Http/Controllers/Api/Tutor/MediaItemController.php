<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\LessonBlockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderMediaItemsRequest;
use App\Http\Requests\Tutor\StoreMediaItemRequest;
use App\Http\Requests\Tutor\UpdateMediaItemRequest;
use App\Http\Resources\MediaItemResource;
use App\Models\LessonBlock;
use App\Models\MediaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaItemController extends Controller
{
    /**
     * Return the media items belonging to a media block, in position order.
     */
    public function index(Request $request, LessonBlock $lessonBlock): JsonResponse
    {
        abort_unless($lessonBlock->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'media_items' => MediaItemResource::collection($lessonBlock->mediaItems),
        ]);
    }

    /**
     * Add a new media item to a media block.
     */
    public function store(StoreMediaItemRequest $request, LessonBlock $lessonBlock): JsonResponse
    {
        $file = $request->file('file');

        $mediaItem = $lessonBlock->mediaItems()->create([
            'media_type' => $request->validated('media_type'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'file_path' => $file?->store('media-items', 'public'),
            'external_url' => $request->validated('external_url'),
            'thumbnail_path' => $request->file('thumbnail')?->store('media-item-thumbnails', 'public'),
            'original_name' => $file?->getClientOriginalName(),
            'size' => $file?->getSize(),
            'position' => $lessonBlock->mediaItems()->count(),
            'status' => $request->validated('status') ?? LessonBlockStatus::Draft->value,
        ]);

        return response()->json([
            'media_item' => new MediaItemResource($mediaItem),
        ], 201);
    }

    /**
     * Update an existing media item.
     */
    public function update(UpdateMediaItemRequest $request, MediaItem $mediaItem): JsonResponse
    {
        $attributes = [
            'media_type' => $request->validated('media_type'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
        ];

        if ($request->hasFile('file')) {
            if ($mediaItem->file_path) {
                Storage::disk('public')->delete($mediaItem->file_path);
            }

            $file = $request->file('file');
            $attributes['file_path'] = $file->store('media-items', 'public');
            $attributes['external_url'] = null;
            $attributes['original_name'] = $file->getClientOriginalName();
            $attributes['size'] = $file->getSize();
        } elseif ($request->filled('external_url')) {
            if ($mediaItem->file_path) {
                Storage::disk('public')->delete($mediaItem->file_path);
            }

            $attributes['file_path'] = null;
            $attributes['external_url'] = $request->validated('external_url');
            $attributes['original_name'] = null;
            $attributes['size'] = null;
        }

        if ($request->hasFile('thumbnail')) {
            if ($mediaItem->thumbnail_path) {
                Storage::disk('public')->delete($mediaItem->thumbnail_path);
            }

            $attributes['thumbnail_path'] = $request->file('thumbnail')->store('media-item-thumbnails', 'public');
        }

        $mediaItem->update($attributes);

        return response()->json([
            'media_item' => new MediaItemResource($mediaItem),
        ]);
    }

    /**
     * Remove a media item.
     */
    public function destroy(Request $request, MediaItem $mediaItem): JsonResponse
    {
        abort_unless(
            $mediaItem->lessonBlock->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        if ($mediaItem->file_path) {
            Storage::disk('public')->delete($mediaItem->file_path);
        }

        if ($mediaItem->thumbnail_path) {
            Storage::disk('public')->delete($mediaItem->thumbnail_path);
        }

        $mediaItem->delete();

        return response()->json([
            'message' => 'Media item deleted.',
        ]);
    }

    /**
     * Persist the drag-and-drop order of a media block's items.
     */
    public function reorder(ReorderMediaItemsRequest $request, LessonBlock $lessonBlock): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('media_item_ids') as $position => $mediaItemId) {
                MediaItem::whereKey($mediaItemId)->update(['position' => $position]);
            }
        });

        return response()->json([
            'media_items' => MediaItemResource::collection($lessonBlock->mediaItems()->get()),
        ]);
    }
}
