<?php

namespace Tests\Feature\Admin;

use App\Mail\AdminTestEmail;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_integration_status_reflects_actual_config(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        config(['services.payfast.merchant_id' => null, 'services.payfast.merchant_key' => null, 'services.payfast.passphrase' => null]);

        $response = $this->getJson('/api/admin/integrations');

        $response->assertOk();
        $response->assertJsonPath('integrations.payfast.status', 'needs_configuration');
    }

    public function test_integration_status_reports_configured_when_config_is_present(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        config(['services.payfast.merchant_id' => 'x', 'services.payfast.merchant_key' => 'x', 'services.payfast.passphrase' => 'x']);

        $response = $this->getJson('/api/admin/integrations');

        $response->assertOk();
        $response->assertJsonPath('integrations.payfast.status', 'configured');
    }

    public function test_system_health_reports_database_healthy(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/system-health');

        $response->assertOk();
        $response->assertJsonPath('checks.database.status', 'healthy');
    }

    public function test_quick_setup_checklist_flags_missing_subjects_as_not_configured(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/quick-setup');

        $response->assertOk();
        $items = collect($response->json('checklist'))->keyBy('key');
        $this->assertSame('not_configured', $items['subjects']['status']);
    }

    public function test_quick_setup_checklist_marks_subjects_complete_once_one_exists(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::create(['name' => 'Mathematics']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/quick-setup');

        $items = collect($response->json('checklist'))->keyBy('key');
        $this->assertSame('complete', $items['subjects']['status']);
    }

    public function test_ready_for_production_is_false_while_required_items_are_incomplete(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/quick-setup');

        $response->assertJsonPath('progress.ready_for_production', false);
    }

    public function test_send_test_email_reports_success_when_sending_succeeds(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/quick-setup/email/test');

        $response->assertOk();
        $response->assertJsonPath('sent', true);
        Mail::assertSent(AdminTestEmail::class);
    }
}
