<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderSelfPacedSurveyQuestionsRequest extends FormRequest
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
            'question_ids' => ['required', 'array'],
            'question_ids.*' => [
                'integer',
                Rule::exists('self_paced_survey_questions', 'id')->where('self_paced_survey_content_id', $this->route('selfPacedSurveyContent')->id),
            ],
        ];
    }
}
