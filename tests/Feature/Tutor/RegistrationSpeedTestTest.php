<?php

namespace Tests\Feature\Tutor;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationSpeedTestTest extends TestCase
{
    use RefreshDatabase;

    private const FAST = ['download_mbps' => 25, 'upload_mbps' => 10, 'latency_ms' => 30, 'jitter_ms' => 5];

    public function test_a_new_unverified_tutor_can_pass_the_speed_test(): void
    {
        $tutorUser = User::factory()->tutor()->unverified()->create();
        TutorProfile::create(['user_id' => $tutorUser->id]);
        Sanctum::actingAs($tutorUser);

        $response = $this->postJson('/api/tutor/speed-test', self::FAST);

        $response->assertOk();
        $response->assertJsonPath('passed', true);
        $response->assertJsonPath('failures', []);
        $response->assertJsonPath('user.tutor_profile.speed_test_passed', true);
        $this->assertNotNull($tutorUser->tutorProfile->fresh()->speed_test_passed_at);
    }

    public function test_a_slow_connection_fails_with_a_reason_for_each_missed_requirement(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id]);
        Sanctum::actingAs($tutorUser);

        $response = $this->postJson('/api/tutor/speed-test', [...self::FAST, 'download_mbps' => 1.2, 'upload_mbps' => 0.8]);

        $response->assertOk();
        $response->assertJsonPath('passed', false);
        $response->assertJsonCount(2, 'failures');
        $response->assertJsonPath('user.tutor_profile.speed_test_passed', false);
        $this->assertNull($tutorUser->tutorProfile->fresh()->speed_test_passed_at);
        $this->assertFalse($tutorUser->tutorProfile->fresh()->speed_test_result['passed']);
    }

    public function test_a_later_slow_test_does_not_revoke_a_pass(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'speed_test_passed_at' => now()]);
        Sanctum::actingAs($tutorUser);

        $response = $this->postJson('/api/tutor/speed-test', [...self::FAST, 'upload_mbps' => 0.5]);

        $response->assertJsonPath('passed', false);
        $response->assertJsonPath('user.tutor_profile.speed_test_passed', true);
    }

    public function test_the_requirements_follow_config(): void
    {
        config(['tutors.speed_test.min_upload_mbps' => 20]);
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id]);
        Sanctum::actingAs($tutorUser);

        $this->getJson('/api/tutor/speed-test')->assertJsonPath('requirements.min_upload_mbps', 20);
        $this->postJson('/api/tutor/speed-test', self::FAST)->assertJsonPath('passed', false);
    }

    public function test_a_tutor_who_has_not_passed_is_blocked_from_their_application(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id]);
        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/tutor/application');

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'TUTOR_SPEED_TEST_REQUIRED');
    }

    public function test_a_tutor_who_has_passed_can_reach_their_application(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'speed_test_passed_at' => now()]);
        Sanctum::actingAs($tutorUser);

        $this->getJson('/api/tutor/application')->assertOk();
    }

    public function test_students_cannot_submit_a_tutor_speed_test(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/tutor/speed-test', self::FAST)->assertForbidden();
    }

    public function test_measurements_are_required(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id]);
        Sanctum::actingAs($tutorUser);

        $this->postJson('/api/tutor/speed-test', [])->assertJsonValidationErrors(['download_mbps', 'upload_mbps', 'latency_ms']);
    }
}
