<?php

namespace App\Http\Requests\Tutor;

use App\Enums\SubmissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReturnSubmissionRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (in_array($this->route('submission')->status, [SubmissionStatus::Draft, SubmissionStatus::Graded], true)) {
                $validator->errors()->add('status', 'Only a submitted submission can be returned for revision.');
            }
        });
    }
}
