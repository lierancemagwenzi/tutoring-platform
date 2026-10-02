<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rich Text, Maths, Mermaid and Media blocks each held a single kind of
 * content. They are superseded by the 'content' block, which holds an
 * ordered list of any of those items. Each existing block becomes a content
 * block with the same content as its items — same row, same id, so
 * session_lesson_blocks and anything else pointing at it are untouched.
 */
return new class extends Migration
{
    private const LEGACY_TYPES = ['rich_text', 'math', 'mermaid', 'media'];

    public function up(): void
    {
        DB::table('lesson_blocks')->whereIn('block_type', self::LEGACY_TYPES)->eachById(function ($block) {
            $content = json_decode($block->content ?? '{}', true) ?? [];

            $items = match ($block->block_type) {
                'rich_text' => [['type' => 'rich_text', 'html' => $content['html'] ?? '', 'json' => $content['json'] ?? null]],
                'math' => [['type' => 'math', 'latex' => $content['latex'] ?? '', 'display_mode' => (bool) ($content['display_mode'] ?? false)]],
                'mermaid' => [['type' => 'mermaid', 'diagram' => $content['diagram'] ?? '']],
                'media' => DB::table('media_items')
                    ->where('lesson_block_id', $block->id)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => ['type' => 'media', 'media_item_id' => $id])
                    ->all(),
            };

            $items = array_map(fn (array $item) => ['id' => (string) Str::uuid(), ...$item], $items);

            DB::table('lesson_blocks')->where('id', $block->id)->update([
                'block_type' => 'content',
                'content' => json_encode(['items' => $items]),
            ]);
        });
    }

    /**
     * Blocks that hold exactly one kind of content go back to their old
     * single-item type. A block mixing kinds (or holding several text /
     * maths / diagram items) has no single-item equivalent, so it is left as
     * a content block.
     */
    public function down(): void
    {
        DB::table('lesson_blocks')->where('block_type', 'content')->eachById(function ($block) {
            $items = json_decode($block->content ?? '{}', true)['items'] ?? [];
            $types = array_values(array_unique(array_column($items, 'type')));

            if ($types === ['media']) {
                DB::table('lesson_blocks')->where('id', $block->id)->update(['block_type' => 'media', 'content' => '{}']);

                return;
            }

            if (count($items) !== 1) {
                return;
            }

            $item = $items[0];

            [$type, $content] = match ($item['type']) {
                'rich_text' => ['rich_text', ['html' => $item['html'] ?? '', 'json' => $item['json'] ?? null]],
                'math' => ['math', ['latex' => $item['latex'] ?? '', 'display_mode' => (bool) ($item['display_mode'] ?? false)]],
                'mermaid' => ['mermaid', ['diagram' => $item['diagram'] ?? '']],
            };

            DB::table('lesson_blocks')->where('id', $block->id)->update(['block_type' => $type, 'content' => json_encode($content)]);
        });
    }
};
