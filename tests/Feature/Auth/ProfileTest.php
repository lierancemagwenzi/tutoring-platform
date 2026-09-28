<?php

namespace Tests\Feature\Auth;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function details(array $overrides = []): array
    {
        return [
            'first_name' => 'Thandi',
            'last_name' => 'Nkosi',
            'phone' => '0821234567',
            'date_of_birth' => '2001-04-12',
            ...$overrides,
        ];
    }

    public function test_every_role_can_update_their_profile_details(): void
    {
        foreach ([User::factory()->create(), User::factory()->tutor()->create(), User::factory()->admin()->create()] as $user) {
            Sanctum::actingAs($user);

            $this->putJson('/api/profile', $this->details())
                ->assertOk()
                ->assertJsonPath('user.first_name', 'Thandi')
                ->assertJsonPath('user.date_of_birth', '2001-04-12');

            $this->assertSame('Nkosi', $user->fresh()->last_name);
        }
    }

    public function test_email_and_role_cannot_be_changed_through_the_profile(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', $this->details(['email' => 'new@example.com', 'role' => 'admin']))->assertOk();

        $this->assertSame('me@example.com', $user->fresh()->email);
        $this->assertSame('student', $user->fresh()->role->value);
    }

    public function test_profile_details_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/profile', ['first_name' => '', 'date_of_birth' => '2999-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'phone', 'date_of_birth']);
    }

    public function test_a_guide_can_update_their_public_profile_and_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->tutor()->create();
        $profile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id, 'display_name' => 'Old Name']);
        Sanctum::actingAs($user);

        $response = $this->put('/api/profile', $this->details([
            'display_name' => 'Ms Nkosi',
            'bio' => 'Maths and science Guide.',
            'profile_photo' => UploadedFile::fake()->image('me.jpg'),
        ]), ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('user.tutor_profile.display_name', 'Ms Nkosi');
        $profile->refresh();
        $this->assertSame('Maths and science Guide.', $profile->bio);
        Storage::disk('public')->assertExists($profile->profile_photo);
        $this->assertNotNull($response->json('user.tutor_profile.profile_photo_url'));
    }

    public function test_guide_only_fields_are_ignored_for_other_roles(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/profile', $this->details(['bio' => 'Not a Guide']))
            ->assertOk()
            ->assertJsonMissingPath('user.tutor_profile.bio');
    }

    public function test_a_user_can_change_their_password_and_other_sessions_are_revoked(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current')->plainTextToken;
        $user->createToken('other-device');

        $this->withToken($current)->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'N3w-Passw0rd!',
            'password_confirmation' => 'N3w-Passw0rd!',
        ])->assertOk();

        $this->assertTrue(Hash::check('N3w-Passw0rd!', $user->fresh()->password));
        $this->assertSame(['current'], $user->tokens()->pluck('name')->all());
    }

    public function test_changing_password_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'N3w-Passw0rd!',
            'password_confirmation' => 'N3w-Passw0rd!',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_be_strong_and_confirmed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_guests_cannot_update_a_profile(): void
    {
        $this->putJson('/api/profile', $this->details())->assertUnauthorized();
        $this->putJson('/api/profile/password', [])->assertUnauthorized();
    }
}
