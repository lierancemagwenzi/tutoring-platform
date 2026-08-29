<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $blocks = DB::table('lesson_blocks')->whereIn('block_type', ['pdf', 'video'])->get();

        foreach ($blocks as $block) {
            $content = json_decode($block->content ?? '{}', true) ?? [];

            $mediaItem = match ($block->block_type) {
                'pdf' => [
                    'media_type' => 'pdf',
                    'file_path' => $content['path'] ?? null,
                    'original_name' => $content['original_name'] ?? null,
                    'size' => $content['size'] ?? null,
                    'external_url' => null,
                ],
                'video' => match ($content['source'] ?? 'upload') {
                    'external' => [
                        'media_type' => ($content['provider'] ?? 'youtube') === 'vimeo' ? 'video_vimeo' : 'video_youtube',
                        'file_path' => null,
                        'original_name' => null,
                        'size' => null,
                        'external_url' => $content['url'] ?? null,
                    ],
                    default => [
                        'media_type' => 'video_upload',
                        'file_path' => $content['path'] ?? null,
                        'original_name' => null,
                        'size' => null,
                        'external_url' => null,
                    ],
                },
            };

            DB::table('media_items')->insert([
                'lesson_block_id' => $block->id,
                'media_type' => $mediaItem['media_type'],
                'title' => $block->title ?? ucfirst($block->block_type),
                'file_path' => $mediaItem['file_path'],
                'external_url' => $mediaItem['external_url'],
                'original_name' => $mediaItem['original_name'],
                'size' => $mediaItem['size'],
                'position' => 0,
                'status' => $block->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('lesson_blocks')->where('id', $block->id)->update([
                'block_type' => 'media',
                'content' => '{}',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward-only data migration; reversing would require re-deriving pdf/video
        // content from media_items, which is lossy in the other direction and not needed.
    }
};
