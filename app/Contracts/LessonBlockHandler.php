<?php

namespace App\Contracts;

use App\Models\LessonBlock;
use Illuminate\Http\Request;

interface LessonBlockHandler
{
    /**
     * The validation rules for this block type's own fields (merged with the
     * base block_type/title/status rules by the calling Form Request).
     *
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array;

    /**
     * Assemble the `content` payload to store on the lesson block.
     *
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array;

    /**
     * Perform any cleanup needed before a block of this type is deleted
     * (e.g. removing stored files).
     */
    public function afterDelete(LessonBlock $block): void;

    /**
     * Assemble the `content` payload for a duplicate of the given block,
     * cloning any owned sub-resources onto the new (already-persisted) copy.
     *
     * @return array<string, mixed>
     */
    public function duplicateContent(LessonBlock $original, LessonBlock $copy): array;
}
