<?php

namespace Tests\Feature\Tutor;

use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\MediaItem;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $tutor;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);

        $this->tutor = User::factory()->tutor()->create();
        $profile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $this->tutor->id]);
        $course = $profile->courses()->create([
            'curriculum_id' => $curriculum->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id,
            'title' => 'Algebra', 'description' => 'Intro.', 'estimated_duration_minutes' => 60,
            'difficulty' => 'beginner', 'language' => 'English', 'status' => 'draft',
        ]);
        $chapter = $course->chapters()->create(['title' => 'Intro', 'position' => 0, 'status' => 'draft']);
        $this->lesson = $chapter->lessons()->create(['title' => 'Variables', 'position' => 0, 'status' => 'draft']);
    }

    private function createContentBlock(): LessonBlock
    {
        Sanctum::actingAs($this->tutor);

        $id = $this->postJson("/api/tutor/lessons/{$this->lesson->id}/blocks", ['block_type' => 'content'])
            ->assertCreated()
            ->assertJsonPath('block.content.items', [])
            ->json('block.id');

        return LessonBlock::findOrFail($id);
    }

    private function uploadPdf(LessonBlock $block, string $title = 'Worksheet'): int
    {
        return $this->post("/api/tutor/lesson-blocks/{$block->id}/media-items", [
            'media_type' => 'pdf',
            'title' => $title,
            'file' => UploadedFile::fake()->create("{$title}.pdf", 50, 'application/pdf'),
            'status' => 'published',
        ], ['Accept' => 'application/json'])->assertCreated()->json('media_item.id');
    }

    private function saveItems(LessonBlock $block, array $items)
    {
        return $this->putJson("/api/tutor/lesson-blocks/{$block->id}", [
            'block_type' => 'content', 'title' => 'Photosynthesis', 'status' => 'published', 'items' => $items,
        ]);
    }

    public function test_a_block_can_hold_many_items_of_mixed_types_in_order(): void
    {
        $block = $this->createContentBlock();
        $pdfA = $this->uploadPdf($block, 'Sheet A');
        $pdfB = $this->uploadPdf($block, 'Sheet B');

        $items = [
            ['type' => 'rich_text', 'html' => '<p>Intro</p>', 'json' => ['type' => 'doc']],
            ['type' => 'mermaid', 'diagram' => 'flowchart TD; A-->B'],
            ['type' => 'rich_text', 'html' => '<p>Explains the diagram</p>'],
            ['type' => 'mermaid', 'diagram' => 'flowchart TD; C-->D'],
            ['type' => 'math', 'latex' => 'E=mc^2', 'display_mode' => true],
            ['type' => 'media', 'media_item_id' => $pdfA],
            ['type' => 'media', 'media_item_id' => $pdfB],
        ];

        $response = $this->saveItems($block, $items)->assertOk();

        $this->assertSame(
            ['rich_text', 'mermaid', 'rich_text', 'mermaid', 'math', 'media', 'media'],
            array_column($response->json('block.content.items'), 'type'),
        );
        $this->assertSame([$pdfA, $pdfB], array_column(array_slice($response->json('block.content.items'), 5), 'media_item_id'));
        $this->assertCount(2, $response->json('block.media_items'));
        $this->assertNotEmpty($response->json('block.content.items.0.id'));
    }

    public function test_items_are_validated_per_type(): void
    {
        $block = $this->createContentBlock();

        $this->saveItems($block, [
            ['type' => 'rich_text'],
            ['type' => 'math'],
            ['type' => 'mermaid'],
            ['type' => 'media'],
            ['type' => 'quiz'],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'items.0.html', 'items.1.latex', 'items.2.diagram', 'items.3.media_item_id', 'items.4.type',
        ]);
    }

    public function test_a_file_from_another_block_cannot_be_referenced(): void
    {
        $block = $this->createContentBlock();
        $other = $this->createContentBlock();
        $foreignPdf = $this->uploadPdf($other);

        $this->saveItems($block, [['type' => 'media', 'media_item_id' => $foreignPdf]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_files_removed_from_the_list_are_deleted_on_save(): void
    {
        $block = $this->createContentBlock();
        $keep = $this->uploadPdf($block, 'Keep');
        $drop = $this->uploadPdf($block, 'Drop');
        $dropPath = MediaItem::find($drop)->file_path;

        $this->saveItems($block, [['type' => 'media', 'media_item_id' => $keep]])->assertOk();

        $this->assertNull(MediaItem::find($drop));
        $this->assertNotNull(MediaItem::find($keep));
        Storage::disk('public')->assertMissing($dropPath);
    }

    public function test_duplicating_copies_items_and_deleting_the_copy_keeps_the_originals_files(): void
    {
        $block = $this->createContentBlock();
        $pdf = $this->uploadPdf($block);
        $this->saveItems($block, [
            ['type' => 'rich_text', 'html' => '<p>Hi</p>'],
            ['type' => 'media', 'media_item_id' => $pdf],
        ])->assertOk();
        $path = MediaItem::find($pdf)->file_path;

        $copy = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/duplicate")->assertCreated();
        $copiedMediaId = $copy->json('block.content.items.1.media_item_id');

        $this->assertNotSame($pdf, $copiedMediaId);
        $this->assertSame($copy->json('block.id'), MediaItem::find($copiedMediaId)->lesson_block_id);

        $this->deleteJson('/api/tutor/lesson-blocks/'.$copy->json('block.id'))->assertOk();

        Storage::disk('public')->assertExists($path);
    }

    public function test_content_blocks_are_offered_and_render_their_media_for_the_tutor(): void
    {
        $block = $this->createContentBlock();
        $pdf = $this->uploadPdf($block);
        $this->saveItems($block, [['type' => 'media', 'media_item_id' => $pdf]])->assertOk();

        $this->getJson("/api/tutor/lessons/{$this->lesson->id}/blocks")
            ->assertOk()
            ->assertJsonPath('blocks.0.block_type', 'content')
            ->assertJsonPath('blocks.0.media_items.0.id', $pdf);
    }

    public function test_legacy_single_item_blocks_are_converted_to_content_blocks(): void
    {
        $make = fn (string $type, array $content) => $this->lesson->blocks()->create([
            'block_type' => $type, 'position' => 0, 'content' => $content, 'settings' => [], 'status' => 'published',
        ]);
        $text = $make('rich_text', ['html' => '<p>Hello</p>', 'json' => ['type' => 'doc']]);
        $math = $make('math', ['latex' => 'x^2', 'display_mode' => true]);
        $diagram = $make('mermaid', ['diagram' => 'flowchart TD; A-->B']);
        $media = $make('media', []);
        $second = $media->mediaItems()->create(['media_type' => 'pdf', 'title' => 'B', 'position' => 1, 'status' => 'published']);
        $first = $media->mediaItems()->create(['media_type' => 'pdf', 'title' => 'A', 'position' => 0, 'status' => 'published']);
        // Something that must be left alone.
        $quiz = $make('quiz', ['quiz_id' => null]);

        $migration = require database_path('migrations/2026_10_02_090000_convert_single_item_blocks_to_content_blocks.php');
        $migration->up();

        $this->assertSame('content', $text->fresh()->block_type->value);
        $this->assertSame('<p>Hello</p>', $text->fresh()->content['items'][0]['html']);
        $this->assertSame('x^2', $math->fresh()->content['items'][0]['latex']);
        $this->assertSame('flowchart TD; A-->B', $diagram->fresh()->content['items'][0]['diagram']);
        $this->assertSame([$first->id, $second->id], array_column($media->fresh()->content['items'], 'media_item_id'));
        $this->assertSame('quiz', $quiz->fresh()->block_type->value);

        $migration->down();

        $this->assertSame('rich_text', $text->fresh()->block_type->value);
        $this->assertSame('<p>Hello</p>', $text->fresh()->content['html']);
        $this->assertSame('media', $media->fresh()->block_type->value);
    }
}
