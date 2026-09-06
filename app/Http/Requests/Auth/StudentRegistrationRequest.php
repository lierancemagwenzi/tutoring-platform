<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StudentRegistrationRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isMinor = $this->isMinor();

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'is_minor' => ['required', 'boolean'],
            'terms_accepted' => ['required', 'accepted'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'guardian_first_name' => [Rule::requiredIf($isMinor), 'string', 'max:255'],
            'guardian_last_name' => [Rule::requiredIf($isMinor), 'string', 'max:255'],
            'guardian_email' => [Rule::requiredIf($isMinor), 'email', 'max:255'],
            'guardian_phone' => [Rule::requiredIf($isMinor), 'string', 'max:20'],
            'relationship_to_student' => [Rule::requiredIf($isMinor), 'string', 'max:255'],
        ];
    }

    /**
     * Determine whether the frontend has flagged this student as a minor (under 18).
     *
     * This is supplied directly by the registration wizard's age-gate step rather
     * than being derived from a date of birth, which the frontend does not collect.
     */
    public function isMinor(): bool
    {
        return $this->boolean('is_minor');
    }
}
