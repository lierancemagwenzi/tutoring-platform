<?php

namespace App\Http\Requests\Student;

use App\Enums\QuizAttemptStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitQuizAttemptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt->student_id === $this->user()->id
            && $attempt->status === QuizAttemptStatus::InProgress;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.question_id' => [
                'required',
                'integer',
                Rule::exists('quiz_questions', 'id')->where('quiz_id', $this->route('attempt')->quiz_id),
            ],
            'answers.*.answer' => ['present'],
        ];
    }
}
