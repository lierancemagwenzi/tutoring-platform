<?php

namespace App\Http\Requests\Tutor;

use App\Enums\SubmissionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GradeSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission->sessionLessonBlock->sessionLesson->teachingSession->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The score's ceiling comes from the Lesson Block's own Maximum Score
     * (an intrinsic content property) — not re-specified here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxScore = $this->route('submission')->sessionLessonBlock->lessonBlock->learningActivity()?->max_score;

        return [
            'score' => array_filter(['required', 'numeric', 'min:0', $maxScore !== null ? "max:{$maxScore}" : null]),
            'feedback_text_html' => ['nullable', 'string'],
            'feedback_text_json' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->route('submission')->status === SubmissionStatus::Draft) {
                $validator->errors()->add('status', 'A draft has not been submitted yet.');
            }
        });
    }
}
