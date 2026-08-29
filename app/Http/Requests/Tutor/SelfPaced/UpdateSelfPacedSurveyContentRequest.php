<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSelfPacedSurveyContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedSurveyContent')->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'grade_id' => ['sometimes', 'required', 'integer', 'exists:grades,id'],
            'subject_id' => ['sometimes', 'required', 'integer', 'exists:subjects,id'],
            'curriculum_id' => ['sometimes', 'required', 'integer', 'exists:curricula,id'],
        ];
    }
}
