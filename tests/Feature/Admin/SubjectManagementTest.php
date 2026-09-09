<?php

namespace Tests\Feature\Admin;

use App\Enums\TutorSubjectStatus;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_create_a_subject_with_an_auto_generated_slug(): void
    {
        $this->admin();

        $response = $this->postJson('/api/admin/subjects', ['name' => 'Advanced Mathematics']);

        $response->assertCreated();
        $response->assertJsonPath('subject.slug', 'advanced-mathematics');
        $response->assertJsonPath('subject.status', 'active');
        $this->assertDatabaseHas('subjects', ['name' => 'Advanced Mathematics', 'is_active' => true]);
    }

    public function test_admin_can_list_subjects_with_counts(): void
    {
        $this->admin();
        $subject = Subject::create(['name' => 'Physics']);
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        TutorSubject::create(['tutor_profile_id' => $tutor->id, 'subject_id' => $subject->id, 'status' => TutorSubjectStatus::Approved]);

        $response = $this->getJson('/api/admin/subjects');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Physics', 'approved_tutors_count' => 1]);
    }

    public function test_admin_can_deactivate_and_reactivate_a_subject(): void
    {
        $this->admin();
        $subject = Subject::create(['name' => 'Chemistry']);

        $deactivate = $this->postJson("/api/admin/subjects/{$subject->id}/deactivate");
        $deactivate->assertOk();
        $deactivate->assertJsonPath('subject.status', 'inactive');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'is_active' => false]);

        $activate = $this->postJson("/api/admin/subjects/{$subject->id}/activate");
        $activate->assertOk();
        $activate->assertJsonPath('subject.status', 'active');
    }

    public function test_admin_can_archive_a_subject_without_deleting_it(): void
    {
        $this->admin();
        $subject = Subject::create(['name' => 'Biology']);

        $response = $this->postJson("/api/admin/subjects/{$subject->id}/archive");

        $response->assertOk();
        $response->assertJsonPath('subject.status', 'archived');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
        $this->assertNotNull(Subject::find($subject->id));
    }

    public function test_archived_subject_is_no_longer_active_scope(): void
    {
        $active = Subject::create(['name' => 'Active Subject']);
        $archived = Subject::create(['name' => 'Archived Subject']);
        $archived->update(['status' => 'archived']);

        $activeIds = Subject::active()->pluck('id');

        $this->assertTrue($activeIds->contains($active->id));
        $this->assertFalse($activeIds->contains($archived->id));
    }

    public function test_subject_overview_reports_offering_counts(): void
    {
        $this->admin();
        $subject = Subject::create(['name' => 'Geography']);

        $response = $this->getJson("/api/admin/subjects/{$subject->id}");

        $response->assertOk();
        $response->assertJsonPath('subject.name', 'Geography');
        $response->assertJsonPath('subject.services_count', 0);
        $response->assertJsonPath('subject.self_paced_courses_count', 0);
    }
}
