<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Shares OtpService with AccountVerificationService rather than rolling its
 * own OTP handling — the only thing specific to password reset is what
 * happens after a code verifies (updating the password instead of flipping
 * email_verified_at) and that this flow never requires authentication.
 */
class PasswordResetService
{
    public function __construct(
        private readonly OtpService $otp,
    ) {}

    /**
     * Issue a password-reset OTP if the email belongs to an account. Never
     * throws and never reveals whether the account exists — the controller
     * returns the same generic response either way.
     */
    public function request(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        $generated = $this->otp->generate($user, OtpPurpose::PasswordReset);

        Mail::to($user->email)->queue(new PasswordResetOtpMail(
            firstName: $user->first_name,
            code: $generated->code,
            expiresInMinutes: 10,
        ));
    }

    /**
     * Verify the code and set the new password. A missing account fails
     * with the same generic message as a wrong code, so the response never
     * discloses whether the email is registered.
     */
    public function reset(string $email, string $code, string $password): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'otp' => 'This code is invalid or has expired.',
            ]);
        }

        $this->otp->verify($user, OtpPurpose::PasswordReset, $code);

        $user->update(['password' => $password]);
    }
}
