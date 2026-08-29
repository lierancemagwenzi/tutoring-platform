<?php

namespace Tests\Feature\Admin;

use App\Mail\AdminInviteMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function regularAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_super_admin_can_invite_a_new_admin(): void
    {
        Mail::fake();
        $this->superAdmin();

        $response = $this->postJson('/api/admin/admins', [
            'first_name' => 'Nomvula', 'last_name' => 'Dlamini', 'email' => 'nomvula@example.test',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('admin.email', 'nomvula@example.test');
        $response->assertJsonPath('admin.is_super_admin', false);
        $response->assertJsonPath('admin.is_pending', true);

        $invitee = User::where('email', 'nomvula@example.test')->first();
        $this->assertNotNull($invitee);
        $this->assertNull($invitee->email_verified_at);
        $this->assertDatabaseHas('email_otps', ['user_id' => $invitee->id, 'purpose' => 'admin_invite']);
        Mail::assertQueued(AdminInviteMail::class, fn ($mail) => $mail->hasTo('nomvula@example.test'));
    }

    public function test_a_regular_admin_cannot_invite_deactivate_or_delete_admins(): void
    {
        $regular = $this->regularAdmin();
        $otherAdmin = $this->regularAdmin();
        Sanctum::actingAs($regular);

        $this->postJson('/api/admin/admins', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test'])->assertForbidden();
        $this->postJson("/api/admin/admins/{$otherAdmin->id}/deactivate")->assertForbidden();
        $this->deleteJson("/api/admin/admins/{$otherAdmin->id}")->assertForbidden();
        $this->getJson('/api/admin/admins')->assertForbidden();
    }

    public function test_super_admin_can_deactivate_a_regular_admin_and_it_blocks_login(): void
    {
        $this->superAdmin();
        $target = $this->regularAdmin();

        $response = $this->postJson("/api/admin/admins/{$target->id}/deactivate");

        $response->assertOk();
        $response->assertJsonPath('admin.disabled', true);
        $this->assertNotNull($target->fresh()->disabled_at);

        $login = $this->postJson('/api/login', ['email' => $target->email, 'password' => 'password']);
        $login->assertStatus(422);
    }

    public function test_super_admin_can_activate_a_deactivated_admin(): void
    {
        $this->superAdmin();
        $target = $this->regularAdmin();
        $target->update(['disabled_at' => now()]);

        $response = $this->postJson("/api/admin/admins/{$target->id}/activate");

        $response->assertOk();
        $response->assertJsonPath('admin.disabled', false);
        $this->assertNull($target->fresh()->disabled_at);
    }

    public function test_super_admin_can_delete_a_regular_admin(): void
    {
        $this->superAdmin();
        $target = $this->regularAdmin();

        $response = $this->deleteJson("/api/admin/admins/{$target->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_a_super_admin_target_cannot_be_deactivated_or_deleted(): void
    {
        $this->superAdmin();
        $otherSuperAdmin = User::factory()->admin()->create();
        $otherSuperAdmin->forceFill(['is_super_admin' => true])->save();

        $this->postJson("/api/admin/admins/{$otherSuperAdmin->id}/deactivate")->assertStatus(422);
        $this->deleteJson("/api/admin/admins/{$otherSuperAdmin->id}")->assertStatus(422);
    }

    public function test_super_admin_cannot_deactivate_or_delete_their_own_account(): void
    {
        $admin = $this->superAdmin();

        $this->postJson("/api/admin/admins/{$admin->id}/deactivate")->assertStatus(422);
        $this->deleteJson("/api/admin/admins/{$admin->id}")->assertStatus(422);
    }

    public function test_generic_disable_endpoint_rejects_an_admin_target_but_still_works_for_a_tutor(): void
    {
        $this->superAdmin();
        $adminTarget = $this->regularAdmin();
        $tutorTarget = User::factory()->tutor()->create();

        $this->postJson("/api/admin/users/{$adminTarget->id}/disable")->assertStatus(422);
        $this->postJson("/api/admin/users/{$tutorTarget->id}/disable")->assertOk();
        $this->assertNotNull($tutorTarget->fresh()->disabled_at);
    }
}
