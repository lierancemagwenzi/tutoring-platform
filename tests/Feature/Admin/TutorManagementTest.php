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

class TutorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_tutors(): void
    {
        $admin = User::factory()->admin()->create();
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/tutor-profiles');

        $response->assertOk();
        $response->assertJsonPath('tutors.0.display_name', 'Test Tutor');
    }

    public function test_admin_can_view_tutor_detail_with_subjects_grouped_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $tutorUser = User::factory()->tutor()->create(['phone' => '0821234567']);
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor', 'bio' => 'Loves teaching.']);
        $approvedSubject = Subject::create(['name' => 'Mathematics']);
        $pendingSubject = Subject::create(['name' => 'Physics']);
        TutorSubject::create(['tutor_profile_id' => $tutor->id, 'subject_id' => $approvedSubject->id, 'status' => TutorSubjectStatus::Approved]);
        TutorSubject::create(['tutor_profile_id' => $tutor->id, 'subject_id' => $pendingSubject->id, 'status' => TutorSubjectStatus::Pending]);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/admin/tutor-profiles/{$tutor->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'tutor.approved_subjects');
        $response->assertJsonCount(1, 'tutor.pending_subjects');
        $response->assertJsonPath('tutor.approved_subjects.0.subject', 'Mathematics');
        $response->assertJsonPath('tutor.pending_subjects.0.subject', 'Physics');
        $response->assertJsonPath('tutor.user.phone', '0821234567');
        $response->assertJsonPath('tutor.profile.bio', 'Loves teaching.');
    }

    public function test_tutor_detail_never_exposes_connected_account_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $tutor->connectedAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'abc123',
            'email' => 'tutor@gmail.com',
            'access_token' => 'super-secret-access-token',
            'refresh_token' => 'super-secret-refresh-token',
            'connected_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/admin/tutor-profiles/{$tutor->id}");

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('super-secret-access-token', $body);
        $this->assertStringNotContainsString('super-secret-refresh-token', $body);
        $response->assertJsonPath('tutor.connected_meeting_providers.0.provider', 'google');
    }
}
