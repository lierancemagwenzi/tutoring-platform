<?php

namespace App\Http\Requests\Tutor;

use App\Enums\QuizQuestionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuizQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('quiz')->lesson->chapter->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_column(QuizQuestionType::cases(), 'value'))],
            'text' => ['required', 'string', 'max:2000'],
            'choices' => ['nullable', 'array'],
            'choices.*' => ['string', 'max:255'],
            'correct_answer' => ['nullable'],
            'points' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = QuizQuestionType::tryFrom((string) $this->input('type'));

            if (! $type) {
                return;
            }

            if (in_array($type, [QuizQuestionType::SingleChoice, QuizQuestionType::MultipleChoice], true)) {
                if (count((array) $this->input('choices', [])) < 2) {
                    $validator->errors()->add('choices', 'Provide at least two choices.');
                }
            }

            if ($type->isAutoScorable() && ! $this->filled('correct_answer')) {
                $validator->errors()->add('correct_answer', 'A correct answer is required for this question type.');
            }
        });
    }
}
