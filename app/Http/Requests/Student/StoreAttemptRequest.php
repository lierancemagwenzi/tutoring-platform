<?php

namespace App\Http\Requests\Student;

use App\Enums\AttemptStatus;
use App\Enums\SessionLessonBlockVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttemptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('booking')->student_id === $this->user()->id;
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
     *
     * Eligibility (does this block support attempts, is it available and
     * visible, are attempts remaining) is checked here rather than in
     * authorize() so a rejection comes back as a clear validation error —
     * matching StoreSubmissionRequest's convention.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $booking = $this->route('booking');
            $lessonBlock = $this->route('lessonBlock');

            if (! $lessonBlock->attemptProvider()) {
                $validator->errors()->add('lesson_block_id', 'This lesson block does not support attempts.');

                return;
            }

            $sessionLessonBlock = $booking->sessionLessonBlockFor($lessonBlock);

            if (! $sessionLessonBlock || ! $sessionLessonBlock->isAvailable()) {
                $validator->errors()->add('lesson_block_id', 'This content is not currently available.');

                return;
            }

            if ($sessionLessonBlock->visibility === SessionLessonBlockVisibility::Hidden) {
                $validator->errors()->add('lesson_block_id', 'This content is not currently available.');

                return;
            }

            $hasOpenAttempt = $sessionLessonBlock->attempts()
                ->where('student_id', $this->user()->id)
                ->whereIn('status', [AttemptStatus::Started->value, AttemptStatus::InProgress->value])
                ->exists();

            if (! $hasOpenAttempt && ! $sessionLessonBlock->hasAttemptCapacityFor($this->user()->id)) {
                $validator->errors()->add('lesson_block_id', 'You have used the maximum number of attempts for this activity.');
            }
        });
    }
}
