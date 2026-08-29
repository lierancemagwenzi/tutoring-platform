<?php

namespace App\Http\Requests\Tutor;

use App\Models\TutorSubject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTutorSubjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tutorSubject = $this->route('subject');

        if ($tutorSubject instanceof TutorSubject) {
            return $tutorSubject->tutor_profile_id === $this->user()->tutorProfile?->id;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'grade_ids.required' => 'Select at least one grade.',
        ];
    }
}
