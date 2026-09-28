<?php

namespace App\Http\Requests\Account;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Email is deliberately not editable here — changing it would need to
     * go back through OTP verification.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ];

        // Guides also manage the public-facing part of their profile.
        if ($this->user()->role === UserRole::Tutor) {
            $rules += [
                'display_name' => ['sometimes', 'required', 'string', 'max:255'],
                'bio' => ['sometimes', 'required', 'string', 'max:2000'],
                'profile_photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            ];
        }

        return $rules;
    }
}
