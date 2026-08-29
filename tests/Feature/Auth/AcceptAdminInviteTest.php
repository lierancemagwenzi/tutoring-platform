<?php

namespace Tests\Feature\Auth;

use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcceptAdminInviteTest extends TestCase
{
    use RefreshDatabase;

    private function invitedAdmin(): User
    {
        return User::create([
            'first_name' => 'Nomvula', 'last_name' => 'Dlamini', 'email' => 'nomvula@example.test',
            'password' => Hash::make(Str::random(40)), 'role' => UserRole::Admin, 'status' => UserStatus::Approved,
        ]);
    }

    public function test_accepting_an_invite_with_the_correct_code_sets_a_real_password_and_verifies_the_email(): void
    {
        $invitee = $this->invitedAdmin();
        $generated = app(OtpService::class)->generate($invitee, OtpPurpose::AdminInvite);

        $response = $this->postJson('/api/accept-admin-invite', [
            'email' => $invitee->email, 'otp' => $generated->code,
            'password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertOk();
        $invitee->refresh();
        $this->assertNotNull($invitee->email_verified_at);
        $this->assertTrue(Hash::check('NewPass123!', $invitee->password));

        $login = $this->postJson('/api/login', ['email' => $invitee->email, 'password' => 'NewPass123!']);
        $login->assertOk();
    }

    public function test_accepting_with_the_wrong_code_is_rejected(): void
    {
        $invitee = $this->invitedAdmin();
        app(OtpService::class)->generate($invitee, OtpPurpose::AdminInvite);

        $response = $this->postJson('/api/accept-admin-invite', [
            'email' => $invitee->email, 'otp' => '000000',
            'password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertStatus(422);
        $this->assertNull($invitee->fresh()->email_verified_at);
    }

    public function test_an_already_used_code_cannot_be_reused(): void
    {
        $invitee = $this->invitedAdmin();
        $generated = app(OtpService::class)->generate($invitee, OtpPurpose::AdminInvite);

        $this->postJson('/api/accept-admin-invite', [
            'email' => $invitee->email, 'otp' => $generated->code,
            'password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!',
        ])->assertOk();

        $response = $this->postJson('/api/accept-admin-invite', [
            'email' => $invitee->email, 'otp' => $generated->code,
            'password' => 'AnotherPass456!', 'password_confirmation' => 'AnotherPass456!',
        ]);

        $response->assertStatus(422);
    }
}
