<?php

namespace App\Http\Requests\Tutor;

use App\Enums\QualificationLevel;
use App\Models\TutorQualification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TutorQualificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $qualification = $this->route('qualification');

        if ($qualification instanceof TutorQualification) {
            return $qualification->tutor_profile_id === $this->user()->tutorProfile?->id;
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
            'title' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::enum(QualificationLevel::class)],
            'field_of_study' => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'start_year' => ['required', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'is_currently_studying' => ['required', 'boolean'],
            'completion_year' => [
                Rule::requiredIf(! $this->boolean('is_currently_studying')),
                'nullable',
                'integer',
                'gte:start_year',
                'max:'.(date('Y') + 1),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
