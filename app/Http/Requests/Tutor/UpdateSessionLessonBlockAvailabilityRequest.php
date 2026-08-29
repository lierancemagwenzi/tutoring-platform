<?php

namespace App\Http\Requests\Tutor;

use App\Enums\AttemptsMode;
use App\Enums\CompletionCondition;
use App\Enums\SessionLessonBlockAvailabilityMode;
use App\Enums\SessionLessonBlockCompletionMode;
use App\Enums\SessionLessonBlockVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSessionLessonBlockAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('sessionLessonBlock')->sessionLesson->teachingSession->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The newer delivery fields are optional here (unlike on assignment) so
     * that a narrow update — e.g. the "release now" manual-release toggle,
     * which only sends availability_mode/is_manually_released — doesn't have
     * to restate the block's entire delivery configuration; anything
     * omitted falls back to its current stored value (see
     * SessionContentService::deliveryAttributes).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'availability_mode' => ['required', Rule::enum(SessionLessonBlockAvailabilityMode::class)],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'is_manually_released' => ['nullable', 'boolean'],
            'completion_mode' => ['nullable', Rule::enum(SessionLessonBlockCompletionMode::class)],
            'completion_rule' => ['nullable', Rule::enum(CompletionCondition::class)],
            'attempts_mode' => ['nullable', Rule::enum(AttemptsMode::class)],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'passing_score' => ['nullable', 'numeric', 'min:0'],
            'visibility' => ['nullable', Rule::enum(SessionLessonBlockVisibility::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('availability_mode') === SessionLessonBlockAvailabilityMode::ScheduledRelease->value && ! $this->filled('available_from')) {
                $validator->errors()->add('available_from', 'An available-from date is required for scheduled release.');
            }

            if ($this->input('availability_mode') === SessionLessonBlockAvailabilityMode::AssessmentWindow->value) {
                if (! $this->filled('opens_at')) {
                    $validator->errors()->add('opens_at', 'An opening date is required for an assessment window.');
                }

                if (! $this->filled('closes_at')) {
                    $validator->errors()->add('closes_at', 'A closing date is required for an assessment window.');
                }
            }

            if ($this->filled('attempts_mode') && $this->input('attempts_mode') === AttemptsMode::Limited->value && ! $this->filled('max_attempts')) {
                $validator->errors()->add('max_attempts', 'A maximum number of attempts is required.');
            }

            if (
                $this->filled('completion_mode')
                && $this->input('completion_mode') !== SessionLessonBlockCompletionMode::NotTracked->value
                && ! $this->filled('completion_rule')
            ) {
                $validator->errors()->add('completion_rule', 'A completion rule is required when completion is tracked.');
            }
        });
    }
}
