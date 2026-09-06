<?php

namespace App\Services\Admin;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Admin-editable, non-secret platform configuration — platform identity,
 * sender details, and feature toggles. Secrets (PayFast keys, Google OAuth,
 * SMTP password) deliberately stay in .env and are never stored here; see
 * IntegrationStatusService for how those are surfaced (presence only).
 */
class PlatformSettingService
{
    /**
     * Sane defaults so the settings screen has sensible values before an
     * admin has ever saved anything — get() falls back to these when no
     * row exists yet for a key.
     *
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'general.platform_name' => 'ItsLearnable',
        'general.logo_url' => null,
        'general.description' => 'A tutoring and self-paced learning platform.',
        'general.timezone' => 'Africa/Johannesburg',
        'general.currency' => 'ZAR',
        'general.contact_email' => null,
        'email.sender_name' => 'ItsLearnable',
        'email.sender_email' => null,
        'auth.registration_enabled' => true,
        'auth.email_verification_enabled' => true,
        'auth.otp_expiry_minutes' => 10,
        'auth.forgot_password_enabled' => true,
        'courses.certificates_enabled' => true,
        'pricing.booking_fee_enabled' => false,
        'pricing.booking_fee_percentage' => 5,
        'pricing.min_tutor_price' => null,
        'pricing.max_tutor_price' => null,
        'legal.terms_and_conditions' => '',
        'legal.privacy_policy' => '',
    ];

    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("platform_setting.{$key}", now()->addMinutes(10), function () use ($key, $default) {
            $setting = PlatformSetting::where('key', $key)->first();

            if ($setting) {
                return $setting->castValue();
            }

            return self::DEFAULTS[$key] ?? $default;
        });
    }

    public function set(string $key, mixed $value, User $actor): void
    {
        $type = match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_array($value) => 'json',
            default => 'string',
        };

        PlatformSetting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'type' => $type],
        );

        Cache::forget("platform_setting.{$key}");

        $this->logger->log($actor, 'settings.updated', $this->settingModelFor($key), "Updated setting \"{$key}\".", ['key' => $key]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = PlatformSetting::all()->keyBy('key');
        $grouped = [];

        foreach (self::DEFAULTS as $key => $default) {
            $category = Str::before($key, '.');
            $setting = $stored->get($key);
            $grouped[$category][$key] = $setting ? $setting->castValue() : $default;
        }

        return $grouped;
    }

    private function settingModelFor(string $key): PlatformSetting
    {
        return PlatformSetting::where('key', $key)->firstOrFail();
    }
}
