<?php

namespace Tests\Feature\Tutor;

use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaItemTest extends TestCase
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

    private function tutorWithMediaBlock(): array
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

        $chapter = $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'draft']);
        $lesson = $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'draft']);
        $block = $lesson->blocks()->create([
            'block_type' => 'media',
            'position' => 0,
            'content' => [],
            'settings' => [],
            'status' => 'draft',
        ]);

        return [$user->fresh(), $block];
    }

    public function test_tutor_can_list_media_items_in_position_order(): void
    {
        [$tutor, $block] = $this->tutorWithMediaBlock();
        $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'Second', 'file_path' => 'b.pdf', 'position' => 1, 'status' => 'draft']);
        $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'First', 'file_path' => 'a.pdf', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/lesson-blocks/{$block->id}/media-items");

        $response->assertOk()->assertJsonCount(2, 'media_items');
        $response->assertJsonPath('media_items.0.title', 'First');
        $response->assertJsonPath('media_items.1.title', 'Second');
    }

    public function test_file_extension_must_match_the_selected_media_type(): void
    {
        [$tutor, $block] = $this->tutorWithMediaBlock();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/lesson-blocks/{$block->id}/media-items", [
            'media_type' => 'pdf',
            'title' => 'Wrong Type',
            'file' => UploadedFile::fake()->create('notes.docx', 100),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_tutor_can_update_a_media_item(): void
    {
        [$tutor, $block] = $this->tutorWithMediaBlock();
        $mediaItem = $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'Old Title', 'file_path' => 'a.pdf', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->putJson("/api/tutor/media-items/{$mediaItem->id}", [
            'media_type' => 'pdf',
            'title' => 'New Title',
            'status' => 'published',
        ]);

        $response->assertOk();
        $response->assertJsonPath('media_item.title', 'New Title');
        $response->assertJsonPath('media_item.status', 'published');
    }

    public function test_tutor_can_delete_a_media_item(): void
    {
        [$tutor, $block] = $this->tutorWithMediaBlock();
        Storage::disk('public')->put('media-items/a.pdf', 'dummy');
        $mediaItem = $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'Notes', 'file_path' => 'media-items/a.pdf', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->deleteJson("/api/tutor/media-items/{$mediaItem->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('media_items', ['id' => $mediaItem->id]);
        Storage::disk('public')->assertMissing('media-items/a.pdf');
    }

    public function test_tutor_cannot_delete_another_tutors_media_item(): void
    {
        [$owner, $block] = $this->tutorWithMediaBlock();
        [$other] = $this->tutorWithMediaBlock();
        $mediaItem = $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'Notes', 'file_path' => 'a.pdf', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($other);

        $response = $this->deleteJson("/api/tutor/media-items/{$mediaItem->id}");

        $response->assertForbidden();
    }

    public function test_deleting_a_media_block_removes_all_media_item_files(): void
    {
        [$tutor, $block] = $this->tutorWithMediaBlock();
        Storage::disk('public')->put('media-items/a.pdf', 'dummy');
        Storage::disk('public')->put('media-items/b.pdf', 'dummy');
        $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'A', 'file_path' => 'media-items/a.pdf', 'position' => 0, 'status' => 'draft']);
        $block->mediaItems()->create(['media_type' => 'pdf', 'title' => 'B', 'file_path' => 'media-items/b.pdf', 'position' => 1, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->deleteJson("/api/tutor/lesson-blocks/{$block->id}");

        $response->assertOk();
        Storage::disk('public')->assertMissing('media-items/a.pdf');
        Storage::disk('public')->assertMissing('media-items/b.pdf');
        $this->assertDatabaseMissing('media_items', ['lesson_block_id' => $block->id]);
    }
}
