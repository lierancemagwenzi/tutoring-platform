<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The single, reusable OTP module shared by every future verification flow
 * (account verification and password reset today, whatever comes next
 * tomorrow) — callers only ever deal in (User, OtpPurpose), never in
 * provider- or flow-specific details. Codes are always hashed at rest and
 * never handed back to a caller once persisted; only generate() ever sees
 * the plaintext, for the one moment it needs to hand it to a Mailable.
 */
class OtpService
{
    private const LENGTH = 6;

    private const EXPIRES_IN_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    private const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Generate a fresh OTP for the given user/purpose. Any previous unused
     * (unverified) code for the same user/purpose is invalidated first, so
     * only one active code ever exists per purpose.
     */
    public function generate(User $user, OtpPurpose $purpose): GeneratedOtp
    {
        $code = str_pad((string) random_int(0, 999999), self::LENGTH, '0', STR_PAD_LEFT);

        $otp = DB::transaction(function () use ($user, $purpose, $code) {
            EmailOtp::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('verified_at')
                ->delete();

            return EmailOtp::create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'otp' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
            ]);
        });

        return new GeneratedOtp($otp, $code);
    }

    /**
     * Validate a submitted code against the user's active OTP for the given
     * purpose. Throws a ValidationException with a caller-facing message for
     * every rejection reason (missing, expired, exhausted, incorrect). A
     * wrong code counts against the attempt limit; a rejection for any other
     * reason does not. Marking verified_at on success is what "invalidates"
     * the code — it can never be consumed again.
     */
    public function verify(User $user, OtpPurpose $purpose, string $code): EmailOtp
    {
        $otp = EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages([
                'otp' => 'No active verification code found. Please request a new one.',
            ]);
        }

        if ($otp->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => 'This code has expired. Please request a new one.',
            ]);
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'otp' => 'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        if (! Hash::check($code, $otp->otp)) {
            $otp->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => 'The code you entered is incorrect.',
            ]);
        }

        $otp->update(['verified_at' => now()]);

        return $otp;
    }

    /**
     * Throws if a resend was requested before the cooldown for this
     * user/purpose has elapsed, regardless of whether the previous code was
     * ever used — the cooldown exists to rate-limit outbound email, not to
     * gate on validity.
     */
    public function assertCanResend(User $user, OtpPurpose $purpose): void
    {
        $latest = EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $latest) {
            return;
        }

        $availableAt = $latest->created_at->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        if ($availableAt->isFuture()) {
            $secondsRemaining = (int) ceil($availableAt->getTimestamp() - now()->getTimestamp());

            throw ValidationException::withMessages([
                'otp' => "Please wait {$secondsRemaining} seconds before requesting another code.",
            ]);
        }
    }
}
