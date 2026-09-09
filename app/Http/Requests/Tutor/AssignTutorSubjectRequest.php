<?php

namespace App\Http\Requests\Tutor;

use App\Enums\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignTutorSubjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // onboarding_complete just means the application was submitted
            // — the tutor's account still needs an admin decision before
            // they should be requesting subjects to teach.
            if ($this->user()->status !== UserStatus::Approved) {
                $validator->errors()->add('status', 'Your tutor account must be approved before you can add subjects.');
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tutorProfileId = $this->user()->tutorProfile?->id;

        return [
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('is_active', true),
                Rule::unique('tutor_subjects', 'subject_id')->where(
                    fn ($query) => $query->where('tutor_profile_id', $tutorProfileId),
                ),
            ],
            'grade_ids' => ['required', 'array', 'min:1'],
            'grade_ids.*' => ['distinct', 'integer', Rule::exists('grades', 'id')->where('is_active', true)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.unique' => 'You have already added this subject.',
            'grade_ids.required' => 'Select at least one grade.',
        ];
    }
}
