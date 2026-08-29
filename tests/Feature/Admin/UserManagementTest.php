<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_search_users(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['first_name' => 'Zelda']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?search=Zelda');

        $response->assertOk();
        $response->assertJsonPath('users.0.email', $student->email);
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create();
        User::factory()->tutor()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?role=tutor');

        $response->assertOk();
        $this->assertTrue(collect($response->json('users'))->every(fn ($u) => $u['role'] === 'tutor'));
    }

    public function test_admin_can_disable_and_re_enable_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $token = $student->createToken('test')->plainTextToken;
        Sanctum::actingAs($admin);

        $disable = $this->postJson("/api/admin/users/{$student->id}/disable");
        $disable->assertOk();
        $disable->assertJsonPath('user.disabled', true);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $enable = $this->postJson("/api/admin/users/{$student->id}/enable");
        $enable->assertOk();
        $enable->assertJsonPath('user.disabled', false);
    }

    public function test_disabled_user_cannot_log_in(): void
    {
        $student = User::factory()->create(['email' => 'disabled@example.com']);
        $student->update(['disabled_at' => now()]);

        $response = $this->postJson('/api/login', ['email' => 'disabled@example.com', 'password' => 'password']);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_disable_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/users/{$admin->id}/disable");

        $response->assertStatus(422);
    }
}
