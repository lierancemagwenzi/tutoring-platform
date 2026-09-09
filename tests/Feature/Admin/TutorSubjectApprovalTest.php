<?php

namespace Tests\Feature\Admin;

use App\Enums\TutorSubjectStatus;
use App\Enums\UserStatus;
use App\Models\Grade;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TutorSubjectApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function tutorRequestingSubject(): array
    {
        $tutorUser = User::factory()->tutor()->create(['status' => UserStatus::Approved]);
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutor->id,
            'subject_id' => $subject->id,
            'status' => TutorSubjectStatus::Pending,
        ]);

        return [$tutorUser, $tutor, $subject, $tutorSubject];
    }

    public function test_admin_sees_pending_requests(): void
    {
        $this->admin();
        [, , , $tutorSubject] = $this->tutorRequestingSubject();

        $response = $this->getJson('/api/admin/tutor-subject-requests');

        $response->assertOk();
        $response->assertJsonCount(1, 'requests');
        $response->assertJsonPath('requests.0.id', $tutorSubject->id);
    }

    public function test_admin_can_approve_a_tutor_subject_request(): void
    {
        $admin = $this->admin();
        [, , , $tutorSubject] = $this->tutorRequestingSubject();

        $response = $this->postJson("/api/admin/tutor-subject-requests/{$tutorSubject->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('request.status', 'approved');
        $tutorSubject->refresh();
        $this->assertSame(TutorSubjectStatus::Approved, $tutorSubject->status);
        $this->assertNotNull($tutorSubject->approved_at);
        $this->assertSame($admin->id, $tutorSubject->approved_by);
    }

    public function test_admin_can_reject_a_tutor_subject_request_with_a_reason(): void
    {
        $this->admin();
        [, , , $tutorSubject] = $this->tutorRequestingSubject();

        $response = $this->postJson("/api/admin/tutor-subject-requests/{$tutorSubject->id}/reject", ['reason' => 'Not qualified.']);

        $response->assertOk();
        $response->assertJsonPath('request.status', 'rejected');
        $this->assertDatabaseHas('tutor_subjects', ['id' => $tutorSubject->id, 'rejection_reason' => 'Not qualified.']);
    }

    public function test_admin_can_suspend_an_approved_tutor_subject(): void
    {
        $this->admin();
        [, , , $tutorSubject] = $this->tutorRequestingSubject();
        $tutorSubject->update(['status' => TutorSubjectStatus::Approved]);

        $response = $this->postJson("/api/admin/tutor-subject-requests/{$tutorSubject->id}/suspend", ['reason' => 'Quality concerns.']);

        $response->assertOk();
        $response->assertJsonPath('request.status', 'suspended');
    }

    public function test_service_cannot_be_published_under_a_pending_subject_request(): void
    {
        [$tutorUser, $tutor, $subject] = $this->tutorRequestingSubject();
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10]);

        $service = $tutor->services()->create([
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths',
            'description' => 'Tutoring.',
            'price' => 100,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'draft',
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->patchJson("/api/tutor/services/{$service->id}/publish");

        $response->assertStatus(422);
        $response->assertJsonFragment(['eligibility' => ['Your request to teach "Mathematics" is still awaiting approval.']]);
    }

    public function test_service_publishes_once_the_tutor_subject_is_approved(): void
    {
        [$tutorUser, $tutor, $subject, $tutorSubject] = $this->tutorRequestingSubject();
        $tutorSubject->update(['status' => TutorSubjectStatus::Approved]);
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10]);

        $service = $tutor->services()->create([
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths',
            'description' => 'Tutoring.',
            'price' => 100,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'draft',
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->patchJson("/api/tutor/services/{$service->id}/publish");

        $response->assertOk();
        $response->assertJsonPath('service.visibility', 'published');
    }

    public function test_service_cannot_be_published_when_tutor_account_is_not_approved(): void
    {
        [$tutorUser, $tutor, $subject, $tutorSubject] = $this->tutorRequestingSubject();
        $tutorSubject->update(['status' => TutorSubjectStatus::Approved]);
        $tutorUser->update(['status' => UserStatus::Pending]);
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10]);

        $service = $tutor->services()->create([
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths',
            'description' => 'Tutoring.',
            'price' => 100,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'draft',
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->patchJson("/api/tutor/services/{$service->id}/publish");

        $response->assertStatus(422);
        $response->assertJsonFragment(['eligibility' => ['Your tutor account is not yet approved.']]);
    }
}
