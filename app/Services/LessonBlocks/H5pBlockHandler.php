<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Models\LessonBlock;
use Illuminate\Http\Request;

class H5pBlockHandler implements LessonBlockHandler
{
    /**
     * The H5P content itself is authored/selected through the dedicated H5P
     * server (see App\Services\H5p\H5PService); Laravel only stores its
     * content id, which is only meaningful once the block already exists
     * (set via update). There's no local table to validate the id against
     * — the H5P server is the source of truth for whether it exists.
     *
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array
    {
        if (! $isUpdate) {
            return [];
        }

        return [
            'h5p_content_id' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array
    {
        if (! $existing) {
            return [];
        }

        return $request->filled('h5p_content_id')
            ? ['h5p_content_id' => (string) $request->input('h5p_content_id')]
            : $existing->content;
    }

    public function afterDelete(LessonBlock $block): void {}

    /**
     * H5P content is a shared library item, so a duplicated block references
     * the same content rather than cloning it.
     *
     * @return array<string, mixed>
     */
    public function duplicateContent(LessonBlock $original, LessonBlock $copy): array
    {
        return $original->content;
    }
}
