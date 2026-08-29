<?php

namespace App\Http\Requests\Tutor;

use App\Enums\LearningActivityType;
use App\Enums\LessonBlockStatus;
use App\Enums\SubmissionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLearningActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('learningActivity')->lesson->chapter->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Delivery behaviour (availability, completion, attempts, passing score)
     * is configured per session on the Session Lesson Block instead — see
     * AssignSessionLessonBlockRequest / UpdateSessionLessonBlockAvailabilityRequest.
     * This request only covers the reusable educational content itself.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions_html' => ['nullable', 'string'],
            'instructions_json' => ['nullable', 'array'],
            'status' => ['required', Rule::enum(LessonBlockStatus::class)],
            'submission_type' => ['required', Rule::enum(SubmissionType::class)],
            'max_score' => ['nullable', 'numeric', 'min:0'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->route('learningActivity')->type === LearningActivityType::External) {
                $externalUrl = $this->input('settings.external_url');

                if (! $externalUrl) {
                    $validator->errors()->add('settings.external_url', 'An external URL is required.');
                } elseif (! filter_var($externalUrl, FILTER_VALIDATE_URL)) {
                    $validator->errors()->add('settings.external_url', 'The external URL must be a valid URL.');
                }
            }
        });
    }
}
