<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_settings_index_returns_defaults_before_anything_is_saved(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/settings');

        $response->assertOk();
        $this->assertSame('ItsLearnable', $response->json('settings.general')['general.platform_name']);
    }

    public function test_admin_can_update_a_setting_and_it_persists(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/admin/settings', [
            'settings' => ['general.platform_name' => 'My Custom Platform'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('platform_settings', ['key' => 'general.platform_name', 'value' => 'My Custom Platform']);

        $again = $this->getJson('/api/admin/settings');
        $this->assertSame('My Custom Platform', $again->json('settings.general')['general.platform_name']);
    }

    public function test_unknown_setting_key_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/admin/settings', [
            'settings' => ['not.a.real.key' => 'value'],
        ]);

        $response->assertStatus(422);
    }

    public function test_boolean_setting_round_trips_correctly(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings', ['settings' => ['auth.registration_enabled' => false]])->assertOk();

        $response = $this->getJson('/api/admin/settings');
        $this->assertFalse($response->json('settings.auth')['auth.registration_enabled']);
    }
}
