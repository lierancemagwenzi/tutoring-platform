<?php

namespace Tests\Feature\Tutor\SelfPaced;

use App\Models\Enrollment;
use App\Models\SelfPacedActivity;
use App\Models\SelfPacedActivityAttachment;
use App\Models\SelfPacedModule;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfPacedContentActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $tutor;

    private SelfPacedModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->tutor = User::factory()->tutor()->create();
        $profile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $this->tutor->id, 'display_name' => 'Test Tutor']);
        $course = $profile->selfPacedCourses()->create([
            'title' => 'Biology', 'price' => 100, 'currency' => 'ZAR', 'status' => 'published', 'visibility' => 'public',
        ]);
        $this->module = $course->modules()->create([
            'title' => 'Cells', 'position' => 0, 'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
    }

    private function createActivity(array $items = []): SelfPacedActivity
    {
        Sanctum::actingAs($this->tutor);

        $id = $this->postJson("/api/tutor/self-paced-modules/{$this->module->id}/activities", [
            'type' => 'content', 'title' => 'Photosynthesis', 'content' => ['items' => $items],
        ])->assertCreated()->json('activity.id');

        return SelfPacedActivity::findOrFail($id);
    }

    private function uploadPdf(SelfPacedActivity $activity, string $title = 'Worksheet'): int
    {
        return $this->post("/api/tutor/self-paced-activities/{$activity->id}/attachments", [
            'media_type' => 'pdf',
            'title' => $title,
            'file' => UploadedFile::fake()->create("{$title}.pdf", 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('attachment.id');
    }

    private function saveItems(SelfPacedActivity $activity, array $items)
    {
        return $this->putJson("/api/tutor/self-paced-activities/{$activity->id}", ['content' => ['items' => $items]]);
    }

    public function test_an_activity_can_hold_many_items_of_mixed_types_in_order(): void
    {
        $activity = $this->createActivity([
            ['type' => 'rich_text', 'html' => '<p>Intro</p>'],
            ['type' => 'mermaid', 'diagram' => 'flowchart TD; A-->B'],
        ]);
        $pdfA = $this->uploadPdf($activity, 'Sheet A');
        $pdfB = $this->uploadPdf($activity, 'Sheet B');

        $response = $this->saveItems($activity, [
            ['type' => 'rich_text', 'html' => '<p>Intro</p>'],
            ['type' => 'mermaid', 'diagram' => 'flowchart TD; A-->B'],
            ['type' => 'rich_text', 'html' => '<p>Explains the diagram</p>'],
            ['type' => 'mermaid', 'diagram' => 'flowchart TD; C-->D'],
            ['type' => 'math', 'latex' => 'E=mc^2', 'display_mode' => true],
            ['type' => 'media', 'media_item_id' => $pdfA],
            ['type' => 'media', 'media_item_id' => $pdfB],
        ])->assertOk();

        $this->assertSame(
            ['rich_text', 'mermaid', 'rich_text', 'mermaid', 'math', 'media', 'media'],
            array_column($response->json('activity.content.items'), 'type'),
        );
        $response->assertJsonCount(2, 'activity.attachments');
        $response->assertJsonPath('activity.has_required_content', true);
    }

    public function test_an_empty_content_activity_does_not_count_as_complete_for_publishing(): void
    {
        $this->createActivity();

        $this->assertFalse(SelfPacedActivity::firstOrFail()->hasRequiredContent());
    }

    public function test_items_are_validated_per_type(): void
    {
        $activity = $this->createActivity();

        $this->saveItems($activity, [
            ['type' => 'rich_text'],
            ['type' => 'math'],
            ['type' => 'mermaid'],
            ['type' => 'media'],
            ['type' => 'quiz'],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'content.items.0.html', 'content.items.1.latex', 'content.items.2.diagram', 'content.items.3.media_item_id', 'content.items.4.type',
        ]);
    }

    public function test_a_new_activity_cannot_reference_files_and_files_from_other_activities_are_rejected(): void
    {
        $other = $this->createActivity();
        $foreignPdf = $this->uploadPdf($other);

        $this->postJson("/api/tutor/self-paced-modules/{$this->module->id}/activities", [
            'type' => 'content', 'title' => 'New', 'content' => ['items' => [['type' => 'media', 'media_item_id' => $foreignPdf]]],
        ])->assertUnprocessable()->assertJsonValidationErrors('content');

        $activity = $this->createActivity();
        $this->saveItems($activity, [['type' => 'media', 'media_item_id' => $foreignPdf]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_files_removed_from_the_list_are_deleted_on_save(): void
    {
        $activity = $this->createActivity();
        $keep = $this->uploadPdf($activity, 'Keep');
        $drop = $this->uploadPdf($activity, 'Drop');
        $dropPath = SelfPacedActivityAttachment::find($drop)->file_path;

        $this->saveItems($activity, [['type' => 'media', 'media_item_id' => $keep]])->assertOk();

        $this->assertNull(SelfPacedActivityAttachment::find($drop));
        $this->assertNotNull(SelfPacedActivityAttachment::find($keep));
        Storage::disk('public')->assertMissing($dropPath);
    }

    public function test_an_enrolled_learner_receives_the_items_and_their_files(): void
    {
        $activity = $this->createActivity();
        $pdf = $this->uploadPdf($activity);
        $this->saveItems($activity, [
            ['type' => 'rich_text', 'html' => '<p>Read this</p>'],
            ['type' => 'media', 'media_item_id' => $pdf],
        ])->assertOk();

        $student = User::factory()->create();
        Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $this->module->self_paced_course_id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);
        Sanctum::actingAs($student);

        $this->getJson("/api/student/self-paced-courses/{$this->module->self_paced_course_id}/activities/{$activity->id}")
            ->assertOk()
            ->assertJsonPath('activity.type', 'content')
            ->assertJsonCount(2, 'activity.content.items')
            ->assertJsonPath('activity.content.items.1.media_item_id', $pdf)
            ->assertJsonPath('activity.attachments.0.id', $pdf)
            ->assertJsonPath('activity.attachments.0.media_type', 'pdf');
    }
}
