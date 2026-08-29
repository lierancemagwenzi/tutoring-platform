<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TutorApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function pendingTutor(): User
    {
        $tutorUser = User::factory()->tutor()->create(['status' => UserStatus::Pending]);
        TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Pending Tutor']);

        return $tutorUser;
    }

    public function test_admin_sees_pending_tutors_in_the_pending_list(): void
    {
        $this->admin();
        $pending = $this->pendingTutor();
        // An approved tutor should not appear in the pending list.
        $approvedTutorUser = User::factory()->tutor()->create(['status' => UserStatus::Approved]);
        TutorProfile::create(['user_id' => $approvedTutorUser->id, 'display_name' => 'Approved Tutor']);

        $response = $this->getJson('/api/admin/tutors/pending');

        $response->assertOk();
        $response->assertJsonCount(1, 'tutors');
        $response->assertJsonPath('tutors.0.email', $pending->email);
    }

    public function test_admin_can_approve_a_pending_tutor(): void
    {
        $this->admin();
        $tutor = $this->pendingTutor();
        $tutor->tutorProfile->bankAccount()->create([
            'bank_name' => 'Test Bank', 'account_holder_name' => 'Pending Tutor',
            'account_number' => '123456789', 'branch_code' => '000000', 'account_type' => 'savings',
        ]);

        $response = $this->postJson("/api/admin/tutors/{$tutor->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('tutor.status', 'approved');
        $this->assertDatabaseHas('users', ['id' => $tutor->id, 'status' => 'approved']);
    }

    public function test_admin_cannot_approve_a_tutor_without_banking_details(): void
    {
        $this->admin();
        $tutor = $this->pendingTutor();

        $response = $this->postJson("/api/admin/tutors/{$tutor->id}/approve");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $tutor->id, 'status' => 'pending']);
    }

    public function test_admin_can_reject_a_pending_tutor_with_a_reason(): void
    {
        $this->admin();
        $tutor = $this->pendingTutor();

        $response = $this->postJson("/api/admin/tutors/{$tutor->id}/reject", ['reason' => 'Incomplete qualifications.']);

        $response->assertOk();
        $response->assertJsonPath('tutor.status', 'rejected');
        $this->assertDatabaseHas('tutor_profiles', ['user_id' => $tutor->id, 'admin_note' => 'Incomplete qualifications.']);
    }

    public function test_rejecting_a_tutor_requires_a_reason(): void
    {
        $this->admin();
        $tutor = $this->pendingTutor();

        $response = $this->postJson("/api/admin/tutors/{$tutor->id}/reject", []);

        $response->assertStatus(422);
    }

    public function test_admin_can_request_changes_leaving_tutor_pending_with_a_note(): void
    {
        $this->admin();
        $tutor = $this->pendingTutor();

        $response = $this->postJson("/api/admin/tutors/{$tutor->id}/request-changes", ['note' => 'Please upload a clearer ID photo.']);

        $response->assertOk();
        $response->assertJsonPath('tutor.status', 'pending');
        $this->assertDatabaseHas('tutor_profiles', ['user_id' => $tutor->id, 'admin_note' => 'Please upload a clearer ID photo.']);
    }

    public function test_pending_tutor_is_not_blocked_from_logging_in(): void
    {
        $tutor = $this->pendingTutor();

        $response = $this->postJson('/api/login', ['email' => $tutor->email, 'password' => 'password']);

        $response->assertOk();
    }

    public function test_admin_can_view_tutor_detail(): void
    {
        $this->admin();
        $tutor = $this->pendingTutor();

        $response = $this->getJson("/api/admin/tutors/{$tutor->id}");

        $response->assertOk();
        $response->assertJsonPath('tutor.user.email', $tutor->email);
        $response->assertJsonPath('tutor.profile.display_name', 'Pending Tutor');
    }
}
