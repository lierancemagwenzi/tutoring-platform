<?php

namespace Tests\Feature\Tutor;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Curriculum $curriculum;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
    }

    private function tutorWithCourse(): array
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $user->id]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $this->subject->id,
            'status' => 'approved',
        ]);
        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $this->grade->id]);

        $course = $tutorProfile->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'A complete introduction to algebraic concepts.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'draft',
        ]);

        return [$user->fresh(), $course];
    }

    // --- Chapters ---

    public function test_tutor_can_create_a_chapter(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/courses/{$course->id}/chapters", [
            'title' => 'Introduction',
            'description' => 'Getting started.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('chapter.title', 'Introduction');
        $response->assertJsonPath('chapter.position', 0);
    }

    public function test_tutor_cannot_create_a_chapter_for_another_tutors_course(): void
    {
        [$owner, $course] = $this->tutorWithCourse();
        [$other] = $this->tutorWithCourse();

        Sanctum::actingAs($other);

        $response = $this->postJson("/api/tutor/courses/{$course->id}/chapters", [
            'title' => 'Introduction',
        ]);

        $response->assertForbidden();
    }

    public function test_tutor_can_reorder_chapters(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $first = $course->chapters()->create(['title' => 'First', 'position' => 0, 'status' => 'draft']);
        $second = $course->chapters()->create(['title' => 'Second', 'position' => 1, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/courses/{$course->id}/chapters/reorder", [
            'chapter_ids' => [$second->id, $first->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_tutor_cannot_delete_another_tutors_chapter(): void
    {
        [$owner, $course] = $this->tutorWithCourse();
        [$other] = $this->tutorWithCourse();
        $chapter = $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($other);

        $response = $this->deleteJson("/api/tutor/chapters/{$chapter->id}");

        $response->assertForbidden();
    }

    // --- Lessons ---

    private function createChapter(Course $course): Chapter
    {
        return $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'draft']);
    }

    public function test_tutor_can_create_a_lesson(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $chapter = $this->createChapter($course);

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/chapters/{$chapter->id}/lessons", [
            'title' => 'What is Algebra?',
            'estimated_duration_minutes' => 15,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('lesson.title', 'What is Algebra?');
        $response->assertJsonPath('lesson.position', 0);
    }

    public function test_tutor_can_reorder_lessons(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $chapter = $this->createChapter($course);
        $first = $chapter->lessons()->create(['title' => 'First', 'position' => 0, 'status' => 'draft']);
        $second = $chapter->lessons()->create(['title' => 'Second', 'position' => 1, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/chapters/{$chapter->id}/lessons/reorder", [
            'lesson_ids' => [$second->id, $first->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_tutor_cannot_update_another_tutors_lesson(): void
    {
        [$owner, $course] = $this->tutorWithCourse();
        [$other] = $this->tutorWithCourse();
        $chapter = $this->createChapter($course);
        $lesson = $chapter->lessons()->create(['title' => 'First', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($other);

        $response = $this->putJson("/api/tutor/lessons/{$lesson->id}", [
            'title' => 'Hijacked',
            'status' => 'draft',
        ]);

        $response->assertForbidden();
    }

    // --- Lesson Blocks ---

    private function createLesson(Chapter $chapter): Lesson
    {
        return $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'draft']);
    }

    public function test_tutor_can_create_a_rich_text_block(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'rich_text',
            'title' => 'Overview',
            'content_html' => '<p>Hello world</p>',
            'content_json' => ['type' => 'doc', 'content' => []],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('block.block_type', 'rich_text');
        $response->assertJsonPath('block.content.html', '<p>Hello world</p>');
    }

    public function test_rich_text_block_requires_content(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'rich_text',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('content_html');
    }

    public function test_tutor_can_create_a_media_block(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'media',
            'title' => 'Supporting Resources',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('block.block_type', 'media');
        $response->assertJsonPath('block.media_items', []);
    }

    public function test_tutor_can_add_a_pdf_media_item(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $block = $this->createMediaBlock($this->createLesson($this->createChapter($course)));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/media-items", [
            'media_type' => 'pdf',
            'title' => 'Lecture Notes',
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('media_item.media_type', 'pdf');
        $this->assertNotNull($response->json('media_item.url'));
    }

    public function test_media_item_requires_a_file_for_non_external_types(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $block = $this->createMediaBlock($this->createLesson($this->createChapter($course)));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/media-items", [
            'media_type' => 'pdf',
            'title' => 'Lecture Notes',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_tutor_can_add_an_external_video_media_item(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $block = $this->createMediaBlock($this->createLesson($this->createChapter($course)));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/media-items", [
            'media_type' => 'video_youtube',
            'title' => 'Intro Video',
            'external_url' => 'https://www.youtube.com/watch?v=abc123',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('media_item.media_type', 'video_youtube');
        $response->assertJsonPath('media_item.url', 'https://www.youtube.com/watch?v=abc123');
    }

    public function test_external_media_item_requires_a_url(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $block = $this->createMediaBlock($this->createLesson($this->createChapter($course)));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/media-items", [
            'media_type' => 'video_vimeo',
            'title' => 'Intro Video',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('external_url');
    }

    public function test_replacing_a_media_item_file_deletes_the_old_file(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $block = $this->createMediaBlock($this->createLesson($this->createChapter($course)));
        Storage::disk('public')->put('media-items/old.pdf', 'dummy');
        $mediaItem = $block->mediaItems()->create([
            'media_type' => 'pdf',
            'title' => 'Old Notes',
            'file_path' => 'media-items/old.pdf',
            'position' => 0,
            'status' => 'draft',
        ]);

        Sanctum::actingAs($tutor);

        $response = $this->putJson("/api/tutor/media-items/{$mediaItem->id}", [
            'media_type' => 'pdf',
            'title' => 'New Notes',
            'status' => 'draft',
            'file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk();
        Storage::disk('public')->assertMissing('media-items/old.pdf');
    }

    public function test_tutor_can_reorder_media_items(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $block = $this->createMediaBlock($this->createLesson($this->createChapter($course)));
        $first = $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'First', 'file_path' => 'a.pdf', 'position' => 0, 'status' => 'draft']);
        $second = $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'Second', 'file_path' => 'b.pdf', 'position' => 1, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/lesson-blocks/{$block->id}/media-items/reorder", [
            'media_item_ids' => [$second->id, $first->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_tutor_cannot_manage_media_items_on_another_tutors_block(): void
    {
        [$owner, $course] = $this->tutorWithCourse();
        [$other] = $this->tutorWithCourse();
        $block = $this->createMediaBlock($this->createLesson($this->createChapter($course)));

        Sanctum::actingAs($other);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/media-items", [
            'media_type' => 'pdf',
            'title' => 'Lecture Notes',
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]);

        $response->assertForbidden();
    }

    public function test_tutor_can_create_a_math_block(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'math',
            'title' => 'Quadratic Formula',
            'latex' => 'x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}',
            'display_mode' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('block.block_type', 'math');
        $response->assertJsonPath('block.content.display_mode', true);
    }

    public function test_math_block_requires_latex(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'math',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('latex');
    }

    public function test_tutor_can_create_a_quiz_block(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'quiz',
            'title' => 'Chapter Check-in',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('block.block_type', 'quiz');
        $this->assertNotNull($response->json('block.quiz.id'));
        $this->assertDatabaseHas('quizzes', ['lesson_id' => $lesson->id, 'title' => 'Chapter Check-in']);
    }

    public function test_tutor_can_create_a_mermaid_block(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'mermaid',
            'title' => 'Process Flow',
            'diagram' => "flowchart TD\n  A --> B",
        ]);

        $response->assertCreated();
        $response->assertJsonPath('block.block_type', 'mermaid');
        $response->assertJsonPath('block.content.diagram', "flowchart TD\n  A --> B");
    }

    public function test_mermaid_block_requires_a_diagram(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'mermaid',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('diagram');
    }

    public function test_tutor_can_create_an_h5p_block_and_attach_content(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));
        $h5pContentId = '123';

        Http::fake([
            '*/api/content/123' => Http::response([
                'id' => $h5pContentId,
                'title' => 'Interactive Quiz',
                'mainLibrary' => 'H5P.InteractiveVideo',
                'language' => 'en',
            ]),
        ]);

        Sanctum::actingAs($tutor);

        $create = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'h5p',
            'title' => 'Interactive Activity',
        ]);

        $create->assertCreated();
        $create->assertJsonPath('block.block_type', 'h5p');
        $blockId = $create->json('block.id');

        $attach = $this->putJson("/api/tutor/lesson-blocks/{$blockId}", [
            'block_type' => 'h5p',
            'status' => 'draft',
            'h5p_content_id' => $h5pContentId,
        ]);

        $attach->assertOk();
        $attach->assertJsonPath('block.h5p_content.id', $h5pContentId);
    }

    public function test_tutor_can_create_an_assignment_block(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'assignment',
            'title' => 'Essay 1',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('block.block_type', 'assignment');
        $this->assertNotNull($response->json('block.learning_activity.id'));
        $this->assertDatabaseHas('learning_activities', [
            'lesson_id' => $lesson->id,
            'type' => 'assignment',
        ]);
    }

    public function test_tutor_can_create_a_homework_block(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => 'homework',
            'title' => 'Practice Set 1',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('block.block_type', 'homework');
        $this->assertDatabaseHas('learning_activities', [
            'lesson_id' => $lesson->id,
            'type' => 'homework',
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function newLearningActivityBlockTypes(): array
    {
        return [
            'practice' => ['practice'],
            'assessment' => ['assessment'],
            'project' => ['project'],
            'lab' => ['lab'],
            'reflection' => ['reflection'],
            'reading' => ['reading'],
            'external_activity' => ['external_activity'],
        ];
    }

    #[DataProvider('newLearningActivityBlockTypes')]
    public function test_tutor_can_create_each_new_learning_activity_block_type(string $blockType): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lessons/{$lesson->id}/blocks", [
            'block_type' => $blockType,
            'title' => 'New Activity',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('block.block_type', $blockType);
        $this->assertNotNull($response->json('block.learning_activity.id'));
        $this->assertDatabaseHas('learning_activities', [
            'lesson_id' => $lesson->id,
            'type' => $blockType,
        ]);
    }

    public function test_tutor_can_duplicate_a_rich_text_block(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));
        $block = $lesson->blocks()->create([
            'block_type' => 'rich_text', 'title' => 'Original', 'position' => 0,
            'content' => ['html' => '<p>1</p>', 'json' => []], 'settings' => [], 'status' => 'published',
        ]);

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/duplicate");

        $response->assertCreated();
        $response->assertJsonPath('block.title', 'Original (Copy)');
        $response->assertJsonPath('block.status', 'draft');
        $response->assertJsonPath('block.content.html', '<p>1</p>');
        $this->assertSame(2, $lesson->blocks()->count());
    }

    public function test_tutor_can_duplicate_a_media_block_with_its_items(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));
        $block = $this->createMediaBlock($lesson);
        $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'Notes', 'file_path' => 'a.pdf', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/duplicate");

        $response->assertCreated();
        $response->assertJsonCount(1, 'block.media_items');
        $this->assertNotSame($block->id, $response->json('block.id'));
    }

    public function test_tutor_can_duplicate_a_quiz_block_independently(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));
        $quiz = $lesson->quizzes()->create(['title' => 'Original Quiz', 'status' => 'draft', 'settings' => []]);
        $quiz->questions()->create(['position' => 0, 'type' => 'text', 'definition' => ['title' => 'Q1'], 'points' => 1]);
        $block = $lesson->blocks()->create([
            'block_type' => 'quiz', 'position' => 0, 'content' => ['quiz_id' => $quiz->id], 'settings' => [], 'status' => 'draft',
        ]);

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/duplicate");

        $response->assertCreated();
        $newQuizId = $response->json('block.quiz.id');
        $this->assertNotSame($quiz->id, $newQuizId);
        $this->assertSame(1, Quiz::find($newQuizId)->questions()->count());
        $this->assertSame(1, $quiz->questions()->count());
    }

    public function test_tutor_can_duplicate_an_h5p_block_referencing_the_same_content(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));
        $h5pContentId = '123';

        Http::fake([
            '*/api/content/123' => Http::response([
                'id' => $h5pContentId,
                'title' => 'Interactive Quiz',
                'mainLibrary' => 'H5P.InteractiveVideo',
                'language' => 'en',
            ]),
        ]);

        $block = $lesson->blocks()->create([
            'block_type' => 'h5p', 'position' => 0, 'content' => ['h5p_content_id' => $h5pContentId], 'settings' => [], 'status' => 'draft',
        ]);

        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/duplicate");

        $response->assertCreated();
        $response->assertJsonPath('block.h5p_content.id', $h5pContentId);
    }

    private function createMediaBlock(Lesson $lesson): LessonBlock
    {
        return $lesson->blocks()->create([
            'block_type' => 'media',
            'position' => 0,
            'content' => [],
            'settings' => [],
            'status' => 'draft',
        ]);
    }

    public function test_tutor_can_reorder_blocks(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));
        $first = $lesson->blocks()->create([
            'block_type' => 'rich_text', 'position' => 0,
            'content' => ['html' => '<p>1</p>', 'json' => []], 'settings' => [], 'status' => 'draft',
        ]);
        $second = $lesson->blocks()->create([
            'block_type' => 'rich_text', 'position' => 1,
            'content' => ['html' => '<p>2</p>', 'json' => []], 'settings' => [], 'status' => 'draft',
        ]);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/lessons/{$lesson->id}/blocks/reorder", [
            'block_ids' => [$second->id, $first->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_tutor_cannot_delete_another_tutors_block(): void
    {
        [$owner, $course] = $this->tutorWithCourse();
        [$other] = $this->tutorWithCourse();
        $lesson = $this->createLesson($this->createChapter($course));
        $block = $lesson->blocks()->create([
            'block_type' => 'rich_text', 'position' => 0,
            'content' => ['html' => '<p>1</p>', 'json' => []], 'settings' => [], 'status' => 'draft',
        ]);

        Sanctum::actingAs($other);

        $response = $this->deleteJson("/api/tutor/lesson-blocks/{$block->id}");

        $response->assertForbidden();
    }
}
