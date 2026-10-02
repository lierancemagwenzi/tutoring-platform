<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Models\LessonBlock;
use App\Support\ContentItems;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A block holding an ordered list of items — any mix and number of rich
 * text, maths, Mermaid diagrams and files — stored in `content.items`.
 *
 * File items reference this block's own MediaItem rows by id; the files are
 * uploaded through the existing media-item endpoints first, then placed in
 * the list. Saving the list is the source of truth for what the block
 * contains, so media rows it no longer references are removed on save.
 */
class ContentBlockHandler implements LessonBlockHandler
{
    /**
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array
    {
        // A new block starts empty and is filled in its editor page.
        return ContentItems::rules('items', required: $isUpdate);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array
    {
        $items = collect($request->input('items') ?? [])->values();

        $ownMediaIds = $existing ? $existing->mediaItems()->pluck('id') : collect();
        $referencedMediaIds = collect(ContentItems::mediaIds($items));

        $foreign = $referencedMediaIds->diff($ownMediaIds);
        if ($foreign->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'One of the files in this block could not be found. Please re-upload it.',
            ]);
        }

        if ($existing) {
            // Files uploaded in the editor but then removed (or never kept)
            // before saving — nothing renders them, so don't keep them around.
            $existing->mediaItems()
                ->whereNotIn('id', $referencedMediaIds->all())
                ->get()
                ->each(function ($mediaItem) {
                    $mediaItem->deleteFilesIfUnshared();
                    $mediaItem->delete();
                });
        }

        return ['items' => ContentItems::normalise($items)];
    }

    public function afterDelete(LessonBlock $block): void
    {
        foreach ($block->mediaItems as $mediaItem) {
            $mediaItem->deleteFilesIfUnshared();
        }
    }

    /**
     * Copy each media row onto the new block (sharing the stored files) and
     * point the copied list's file items at the new rows.
     *
     * @return array<string, mixed>
     */
    public function duplicateContent(LessonBlock $original, LessonBlock $copy): array
    {
        $idMap = [];

        foreach ($original->mediaItems as $mediaItem) {
            $idMap[$mediaItem->id] = $copy->mediaItems()->create(
                $mediaItem->only([
                    'media_type', 'title', 'description', 'file_path', 'external_url',
                    'thumbnail_path', 'original_name', 'size', 'position', 'status',
                ]),
            )->id;
        }

        $items = collect($original->content['items'] ?? [])
            ->map(function (array $item) use ($idMap) {
                if ($item['type'] === 'media') {
                    $item['media_item_id'] = $idMap[$item['media_item_id']] ?? null;
                }

                return [...$item, 'id' => (string) Str::uuid()];
            })
            ->filter(fn (array $item) => $item['type'] !== 'media' || $item['media_item_id'] !== null)
            ->values()
            ->all();

        return ['items' => $items];
    }
}
