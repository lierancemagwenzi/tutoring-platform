<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Mirrors PasswordResetService's shape exactly (verify an OTP, then act) —
 * the only difference is what "acting" means: setting a real password for
 * the first time and marking the invited admin's email verified, instead of
 * replacing an existing password.
 */
class AdminInvitationAcceptanceService
{
    public function __construct(
        private readonly OtpService $otp,
    ) {}

    public function accept(string $email, string $code, string $password): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'otp' => 'This code is invalid or has expired.',
            ]);
        }

        $this->otp->verify($user, OtpPurpose::AdminInvite, $code);

        $user->update(['password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();
    }
}
