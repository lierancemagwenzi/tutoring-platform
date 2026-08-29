<?php

namespace App\Http\Requests\Tutor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
