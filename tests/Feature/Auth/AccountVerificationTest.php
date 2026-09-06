<?php

namespace Tests\Feature\Auth;

use App\Mail\AccountVerificationOtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function registerStudent(): array
    {
        Mail::fake();

        $response = $this->postJson('/api/register/student', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '0123456789',
            'date_of_birth' => '2000-01-01',
            'is_minor' => false,
            'terms_accepted' => true,
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ]);

        $response->assertCreated();

        $code = null;
        Mail::assertQueued(AccountVerificationOtpMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return [$response->json('token'), $code];
    }

    public function test_registering_creates_an_unverified_account_and_sends_an_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register/student', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '0123456789',
            'date_of_birth' => '2000-01-01',
            'is_minor' => false,
            'terms_accepted' => true,
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.email_verified_at', null);
        $this->assertNotNull($response->json('token'));

        Mail::assertQueued(AccountVerificationOtpMail::class);

        $user = User::where('email', 'ada@example.com')->first();
        $this->assertNull($user->email_verified_at);
        $this->assertSame(1, EmailOtp::where('user_id', $user->id)->count());
    }

    public function test_unverified_user_is_blocked_from_protected_routes_but_can_still_logout_check_self_and_verify(): void
    {
        [$token] = $this->registerStudent();

        $blocked = $this->withToken($token)->getJson('/api/subjects');
        $blocked->assertForbidden();
        $blocked->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');

        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_verifying_with_the_correct_otp_marks_the_account_verified_and_unlocks_protected_routes(): void
    {
        [$token, $code] = $this->registerStudent();

        $response = $this->withToken($token)->postJson('/api/email/verify', ['otp' => $code]);

        $response->assertOk();
        $response->assertJsonPath('user.email_verified_at', fn ($value) => $value !== null);

        $this->withToken($token)->getJson('/api/subjects')->assertOk();
    }

    public function test_verifying_with_an_incorrect_otp_is_rejected_and_counts_as_an_attempt(): void
    {
        [$token] = $this->registerStudent();

        $response = $this->withToken($token)->postJson('/api/email/verify', ['otp' => '000000']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('otp');

        $otp = EmailOtp::first();
        $this->assertSame(1, $otp->attempts);
    }

    public function test_exceeding_the_maximum_attempts_rejects_further_verification_even_with_the_correct_code(): void
    {
        [$token, $code] = $this->registerStudent();

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)->postJson('/api/email/verify', ['otp' => '000000'])->assertUnprocessable();
        }

        $response = $this->withToken($token)->postJson('/api/email/verify', ['otp' => $code]);

        $response->assertUnprocessable();
        $this->assertNull(User::first()->email_verified_at);
    }

    public function test_an_expired_otp_is_rejected(): void
    {
        [$token, $code] = $this->registerStudent();

        EmailOtp::first()->update(['expires_at' => now()->subMinute()]);

        $response = $this->withToken($token)->postJson('/api/email/verify', ['otp' => $code]);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['otp' => ['This code has expired. Please request a new one.']]);
    }

    public function test_resend_respects_the_cooldown(): void
    {
        [$token] = $this->registerStudent();

        $response = $this->withToken($token)->postJson('/api/email/resend');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('otp');
    }

    public function test_resend_after_the_cooldown_issues_a_new_otp_and_invalidates_the_previous_one(): void
    {
        [$token, $originalCode] = $this->registerStudent();

        EmailOtp::first()->forceFill(['created_at' => now()->subMinutes(2)])->save();

        Mail::fake();
        $response = $this->withToken($token)->postJson('/api/email/resend');
        $response->assertOk();

        $this->assertSame(1, EmailOtp::count());

        $newCode = null;
        Mail::assertQueued(AccountVerificationOtpMail::class, function ($mail) use (&$newCode) {
            $newCode = $mail->code;

            return true;
        });

        // The original code no longer verifies once a new one has replaced it.
        $this->withToken($token)->postJson('/api/email/verify', ['otp' => $originalCode])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/email/verify', ['otp' => $newCode])->assertOk();
    }

    public function test_an_already_verified_user_cannot_resend(): void
    {
        [$token, $code] = $this->registerStudent();
        $this->withToken($token)->postJson('/api/email/verify', ['otp' => $code])->assertOk();

        $response = $this->withToken($token)->postJson('/api/email/resend');

        $response->assertUnprocessable();
    }

    public function test_otp_is_stored_hashed_not_in_plain_text(): void
    {
        [, $code] = $this->registerStudent();

        $otp = EmailOtp::first();

        $this->assertNotSame($code, $otp->otp);
        $this->assertTrue(Hash::check($code, $otp->otp));
    }

    public function test_a_user_cannot_verify_their_account_using_a_code_issued_to_another_user(): void
    {
        [, $codeForAda] = $this->registerStudent();

        Mail::fake();
        $secondResponse = $this->postJson('/api/register/student', [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.com',
            'phone' => '0123456789',
            'date_of_birth' => '2000-01-01',
            'is_minor' => false,
            'terms_accepted' => true,
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ]);
        $secondResponse->assertCreated();
        $graceToken = $secondResponse->json('token');

        $response = $this->withToken($graceToken)->postJson('/api/email/verify', ['otp' => $codeForAda]);

        $response->assertUnprocessable();
        $this->assertNull(User::where('email', 'grace@example.com')->first()->email_verified_at);
    }

    public function test_verifying_without_authentication_is_rejected(): void
    {
        $response = $this->postJson('/api/email/verify', ['otp' => '123456']);

        $response->assertUnauthorized();
    }

    public function test_logging_in_does_not_require_an_otp_once_already_verified(): void
    {
        $user = User::factory()->create(['email' => 'verified@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'verified@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('token'));

        $this->withToken($response->json('token'))->getJson('/api/subjects')->assertOk();
    }
}
