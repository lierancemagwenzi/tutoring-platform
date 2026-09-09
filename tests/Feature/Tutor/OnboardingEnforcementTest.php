<?php

namespace Tests\Feature\Tutor;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tutor_with_an_incomplete_application_is_blocked_from_the_rest_of_the_api(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'onboarding_complete' => false]);
        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/tutor/workspace');

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'TUTOR_ONBOARDING_INCOMPLETE');
    }

    public function test_a_tutor_with_a_complete_application_can_use_the_rest_of_the_api(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'onboarding_complete' => true]);
        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
    }

    public function test_a_tutor_with_an_incomplete_application_can_still_reach_the_application_wizard_itself(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'onboarding_complete' => false]);
        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/tutor/application');

        $response->assertOk();
    }
}
