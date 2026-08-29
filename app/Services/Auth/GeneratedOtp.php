<?php

namespace App\Services\Auth;

use App\Models\EmailOtp;

/**
 * The plaintext code only ever exists transiently, as this DTO's return
 * value from OtpService::generate() — it is never persisted (only its hash
 * is) and never appears on the EmailOtp model itself, so it can't leak
 * through an accidental toArray()/JSON serialization of that model.
 */
final readonly class GeneratedOtp
{
    public function __construct(
        public EmailOtp $otp,
        public string $code,
    ) {}
}
