<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // overview() is cached for 5 minutes — each test needs a clean slate
        // rather than a stale result from a previous test's fixtures.
        Cache::flush();
    }

    public function test_dashboard_reports_accurate_user_and_subject_counts(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();
        User::factory()->tutor()->create(['status' => UserStatus::Approved]);
        User::factory()->tutor()->create(['status' => UserStatus::Pending]);
        Subject::create(['name' => 'Active Subject']);
        Subject::create(['name' => 'Inactive Subject'])->update(['status' => 'inactive']);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();
        $response->assertJsonPath('overview.users.total_students', 2);
        $response->assertJsonPath('overview.users.total_tutors', 2);
        $response->assertJsonPath('overview.users.pending_tutor_accounts', 1);
        $response->assertJsonPath('overview.subjects.total_subjects', 2);
        $response->assertJsonPath('overview.subjects.active_subjects', 1);
    }

    public function test_action_required_flags_pending_tutors_and_missing_subjects(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->tutor()->create(['status' => UserStatus::Pending]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();
        $messages = collect($response->json('action_required'))->pluck('message');
        $this->assertTrue($messages->contains('1 tutors awaiting approval'));
        $this->assertTrue($messages->contains('No active subjects configured'));
    }

    public function test_action_required_is_empty_when_platform_is_healthy(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::create(['name' => 'Mathematics']);
        config(['services.payfast.merchant_id' => 'x', 'services.payfast.merchant_key' => 'x', 'services.payfast.passphrase' => 'x']);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com', 'mail.mailers.smtp.username' => 'user']);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();
        $response->assertJsonPath('action_required', []);
    }
}
