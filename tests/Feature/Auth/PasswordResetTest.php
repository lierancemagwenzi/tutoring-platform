<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetOtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_a_generic_response_and_sends_an_otp_for_an_existing_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'exists@example.com']);

        $response = $this->postJson('/api/forgot-password', ['email' => 'exists@example.com']);

        $response->assertOk();
        Mail::assertQueued(PasswordResetOtpMail::class);
        $this->assertSame(1, EmailOtp::where('user_id', $user->id)->count());
    }

    public function test_forgot_password_returns_the_same_generic_response_for_a_nonexistent_email(): void
    {
        Mail::fake();

        $existing = $this->postJson('/api/forgot-password', ['email' => 'unknown@example.com']);

        $existing->assertOk();
        Mail::assertNothingQueued();
        $this->assertSame(0, EmailOtp::count());
    }

    public function test_the_two_responses_are_indistinguishable(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'exists@example.com']);

        $forExisting = $this->postJson('/api/forgot-password', ['email' => 'exists@example.com']);
        $forMissing = $this->postJson('/api/forgot-password', ['email' => 'missing@example.com']);

        $this->assertSame($forExisting->status(), $forMissing->status());
        $this->assertSame($forExisting->json('message'), $forMissing->json('message'));
    }

    public function test_reset_password_with_a_valid_otp_updates_the_password_and_allows_login(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'exists@example.com']);
        $this->postJson('/api/forgot-password', ['email' => 'exists@example.com'])->assertOk();

        $code = null;
        Mail::assertQueued(PasswordResetOtpMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $response = $this->postJson('/api/reset-password', [
            'email' => 'exists@example.com',
            'otp' => $code,
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ]);

        $response->assertOk();

        $login = $this->postJson('/api/login', [
            'email' => 'exists@example.com',
            'password' => 'NewPassword!123',
        ]);
        $login->assertOk();
    }

    public function test_reset_password_does_not_require_authentication(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'exists@example.com']);
        $this->postJson('/api/forgot-password', ['email' => 'exists@example.com']);

        $code = null;
        Mail::assertQueued(PasswordResetOtpMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        // No Authorization header / Sanctum::actingAs anywhere in this test.
        $response = $this->postJson('/api/reset-password', [
            'email' => 'exists@example.com',
            'otp' => $code,
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ]);

        $response->assertOk();
    }

    public function test_reset_password_with_an_invalid_otp_is_rejected(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'exists@example.com']);
        $this->postJson('/api/forgot-password', ['email' => 'exists@example.com']);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'exists@example.com',
            'otp' => '000000',
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_reset_password_for_an_unregistered_email_fails_generically(): void
    {
        $response = $this->postJson('/api/reset-password', [
            'email' => 'missing@example.com',
            'otp' => '123456',
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['otp' => ['This code is invalid or has expired.']]);
    }

    public function test_the_otp_cannot_be_reused_after_a_successful_reset(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'exists@example.com']);
        $this->postJson('/api/forgot-password', ['email' => 'exists@example.com']);

        $code = null;
        Mail::assertQueued(PasswordResetOtpMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/reset-password', [
            'email' => 'exists@example.com',
            'otp' => $code,
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ])->assertOk();

        $reuse = $this->postJson('/api/reset-password', [
            'email' => 'exists@example.com',
            'otp' => $code,
            'password' => 'AnotherPassword!123',
            'password_confirmation' => 'AnotherPassword!123',
        ]);

        $reuse->assertUnprocessable();
    }

    public function test_password_reset_does_not_affect_account_verification_status(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'exists@example.com']);
        $this->postJson('/api/forgot-password', ['email' => 'exists@example.com']);

        $code = null;
        Mail::assertQueued(PasswordResetOtpMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/reset-password', [
            'email' => 'exists@example.com',
            'otp' => $code,
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
