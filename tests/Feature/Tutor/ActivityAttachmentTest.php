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

class ActivityAttachmentTest extends TestCase
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

    private function tutorWithActivity(): array
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
        $activity = $lesson->learningActivities()->create([
            'type' => 'assignment',
            'title' => 'Untitled Assignment',
            'status' => 'draft',
            'submission_type' => 'text',
        ]);

        return [$user->fresh(), $activity];
    }

    public function test_tutor_can_list_attachments_in_position_order(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        $activity->attachments()->create(['media_type' => 'pdf', 'title' => 'Second', 'file_path' => 'b.pdf', 'position' => 1, 'status' => 'draft']);
        $activity->attachments()->create(['media_type' => 'pdf', 'title' => 'First', 'file_path' => 'a.pdf', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/learning-activities/{$activity->id}/attachments");

        $response->assertOk()->assertJsonCount(2, 'attachments');
        $response->assertJsonPath('attachments.0.title', 'First');
    }

    public function test_tutor_can_add_a_pdf_attachment(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/learning-activities/{$activity->id}/attachments", [
            'media_type' => 'pdf',
            'title' => 'Rubric',
            'file' => UploadedFile::fake()->create('rubric.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('attachment.media_type', 'pdf');
        $this->assertNotNull($response->json('attachment.url'));
    }

    public function test_external_video_media_types_are_rejected_for_attachments(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/learning-activities/{$activity->id}/attachments", [
            'media_type' => 'video_youtube',
            'title' => 'Not allowed',
            'file' => UploadedFile::fake()->create('rubric.pdf', 100, 'application/pdf'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('media_type');
    }

    public function test_attachment_requires_a_file(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/learning-activities/{$activity->id}/attachments", [
            'media_type' => 'pdf',
            'title' => 'Rubric',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_tutor_can_reorder_attachments(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        $first = $activity->attachments()->create(['media_type' => 'pdf', 'title' => 'First', 'file_path' => 'a.pdf', 'position' => 0, 'status' => 'draft']);
        $second = $activity->attachments()->create(['media_type' => 'pdf', 'title' => 'Second', 'file_path' => 'b.pdf', 'position' => 1, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/learning-activities/{$activity->id}/attachments/reorder", [
            'attachment_ids' => [$second->id, $first->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_tutor_can_delete_an_attachment(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        Storage::disk('public')->put('activity-attachments/a.pdf', 'dummy');
        $attachment = $activity->attachments()->create(['media_type' => 'pdf', 'title' => 'Rubric', 'file_path' => 'activity-attachments/a.pdf', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->deleteJson("/api/tutor/activity-attachments/{$attachment->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('activity_attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing('activity-attachments/a.pdf');
    }

    public function test_tutor_cannot_manage_attachments_on_another_tutors_activity(): void
    {
        [$owner, $activity] = $this->tutorWithActivity();
        [$other] = $this->tutorWithActivity();

        Sanctum::actingAs($other);

        $response = $this->postJson("/api/tutor/learning-activities/{$activity->id}/attachments", [
            'media_type' => 'pdf',
            'title' => 'Rubric',
            'file' => UploadedFile::fake()->create('rubric.pdf', 100, 'application/pdf'),
        ]);

        $response->assertForbidden();
    }
}
