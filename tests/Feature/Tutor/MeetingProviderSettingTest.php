<?php

namespace Tests\Feature\Tutor;

use App\Models\TutorConnectedAccount;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingProviderSettingTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function connectGoogle(TutorProfile $tutor): void
    {
        TutorConnectedAccount::create([
            'tutor_profile_id' => $tutor->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
            'email' => 'tutor@gmail.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'connected_at' => now(),
        ]);
    }

    public function test_index_returns_no_selection_and_the_implemented_providers(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor->user);

        $response = $this->getJson('/api/tutor/settings/meeting-providers');

        $response->assertOk();
        $response->assertJson(['selected' => null, 'available' => ['google']]);
    }

    public function test_tutor_without_a_connected_google_account_cannot_select_it(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor->user);

        $response = $this->putJson('/api/tutor/settings/meeting-providers', ['provider' => 'google']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('provider');
        $this->assertNull($tutor->fresh()->meeting_provider);
    }

    public function test_tutor_with_a_connected_google_account_can_select_it(): void
    {
        $tutor = $this->tutor();
        $this->connectGoogle($tutor);
        Sanctum::actingAs($tutor->user);

        $response = $this->putJson('/api/tutor/settings/meeting-providers', ['provider' => 'google']);

        $response->assertOk();
        $response->assertJson(['selected' => 'google']);
        $this->assertSame('google', $tutor->fresh()->meeting_provider->value);
    }

    public function test_selection_persists_and_is_returned_by_index(): void
    {
        $tutor = $this->tutor();
        $this->connectGoogle($tutor);
        Sanctum::actingAs($tutor->user);

        $this->putJson('/api/tutor/settings/meeting-providers', ['provider' => 'google'])->assertOk();

        $response = $this->getJson('/api/tutor/settings/meeting-providers');
        $response->assertOk();
        $response->assertJson(['selected' => 'google']);
    }

    public function test_an_unimplemented_provider_is_rejected(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor->user);

        $response = $this->putJson('/api/tutor/settings/meeting-providers', ['provider' => 'zoom']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('provider');
    }

    public function test_student_cannot_access_meeting_provider_settings(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/tutor/settings/meeting-providers')->assertForbidden();
        $this->putJson('/api/tutor/settings/meeting-providers', ['provider' => 'google'])->assertForbidden();
    }
}
