<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Mail\AccountVerificationOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Owns account verification end-to-end: issuing the OTP (at registration and
 * on resend) and consuming it to flip email_verified_at. Verification only
 * ever happens once — once hasVerifiedEmail() is true there's nothing left
 * for this service to do for that user.
 */
class AccountVerificationService
{
    public function __construct(
        private readonly OtpService $otp,
    ) {}

    /**
     * Generate and email a fresh account-verification OTP.
     */
    public function issueOtp(User $user): void
    {
        $generated = $this->otp->generate($user, OtpPurpose::AccountVerification);

        Mail::to($user->email)->queue(new AccountVerificationOtpMail(
            firstName: $user->first_name,
            code: $generated->code,
            expiresInMinutes: 10,
        ));
    }

    /**
     * Respect the resend cooldown, then issue a new OTP invalidating the
     * previous one.
     */
    public function resend(User $user): void
    {
        $this->otp->assertCanResend($user, OtpPurpose::AccountVerification);

        $this->issueOtp($user);
    }

    /**
     * Verify the submitted code and, on success, mark the account verified.
     * Throws a ValidationException (via OtpService) on any invalid code.
     */
    public function verify(User $user, string $code): void
    {
        $this->otp->verify($user, OtpPurpose::AccountVerification, $code);

        $user->markEmailAsVerified();
    }
}
