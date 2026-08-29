<?php

namespace App\Http\Requests\Tutor;

use App\Enums\AttemptsMode;
use App\Enums\CompletionCondition;
use App\Enums\LessonBlockStatus;
use App\Enums\SessionLessonBlockAvailabilityMode;
use App\Enums\SessionLessonBlockCompletionMode;
use App\Enums\SessionLessonBlockVisibility;
use App\Models\LessonBlock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignSessionLessonBlockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('sessionLesson')->teachingSession->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sessionLesson = $this->route('sessionLesson');

        return [
            'lesson_block_id' => [
                'required',
                'integer',
                Rule::exists('lesson_blocks', 'id')->where('lesson_id', $sessionLesson->lesson_id),
                Rule::unique('session_lesson_blocks', 'lesson_block_id')->where('session_lesson_id', $sessionLesson->id),
            ],
            'availability_mode' => ['required', Rule::enum(SessionLessonBlockAvailabilityMode::class)],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'is_manually_released' => ['nullable', 'boolean'],
            'completion_mode' => ['required', Rule::enum(SessionLessonBlockCompletionMode::class)],
            'completion_rule' => ['nullable', Rule::enum(CompletionCondition::class)],
            'attempts_mode' => ['required', Rule::enum(AttemptsMode::class)],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'passing_score' => ['nullable', 'numeric', 'min:0'],
            'visibility' => ['required', Rule::enum(SessionLessonBlockVisibility::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->has('lesson_block_id')) {
                $block = LessonBlock::find($this->input('lesson_block_id'));

                if ($block && $block->status !== LessonBlockStatus::Published) {
                    $validator->errors()->add('lesson_block_id', 'Only published lesson blocks can be assigned to a session.');
                }
            }

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

            if ($this->input('attempts_mode') === AttemptsMode::Limited->value && ! $this->filled('max_attempts')) {
                $validator->errors()->add('max_attempts', 'A maximum number of attempts is required.');
            }

            if ($this->input('completion_mode') !== SessionLessonBlockCompletionMode::NotTracked->value && ! $this->filled('completion_rule')) {
                $validator->errors()->add('completion_rule', 'A completion rule is required when completion is tracked.');
            }
        });
    }
}
