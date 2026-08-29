<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * The only keys the settings endpoint will ever write — anything else
     * in the request payload is rejected rather than silently stored.
     *
     * @var list<string>
     */
    public const KNOWN_KEYS = [
        'general.platform_name',
        'general.logo_url',
        'general.description',
        'general.timezone',
        'general.currency',
        'general.contact_email',
        'email.sender_name',
        'email.sender_email',
        'auth.registration_enabled',
        'auth.email_verification_enabled',
        'auth.otp_expiry_minutes',
        'auth.forgot_password_enabled',
        'courses.certificates_enabled',
        'pricing.booking_fee_enabled',
        'pricing.booking_fee_percentage',
        'pricing.min_tutor_price',
        'pricing.max_tutor_price',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->input('settings', [])) as $key) {
                if (! in_array($key, self::KNOWN_KEYS, true)) {
                    $validator->errors()->add('settings', "Unknown setting key: {$key}.");
                }
            }
        });
    }
}
