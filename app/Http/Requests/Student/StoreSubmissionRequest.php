<?php

namespace App\Http\Requests\Student;

use App\Enums\SubmissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSubmissionRequest extends FormRequest
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
     * Eligibility (does this block accept submissions, is it available, are
     * attempts remaining) is checked here rather than in authorize() so a
     * rejection comes back as a clear validation error instead of a bare
     * 403 — matching how session-assignment eligibility is validated
     * elsewhere (see AssignSessionLessonRequest).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $booking = $this->route('booking');
            $lessonBlock = $this->route('lessonBlock');

            if (! $lessonBlock->supportsSubmissions()) {
                $validator->errors()->add('lesson_block_id', 'This lesson block does not accept submissions.');

                return;
            }

            $sessionLessonBlock = $booking->sessionLessonBlockFor($lessonBlock);

            if (! $sessionLessonBlock || ! $sessionLessonBlock->isAvailable()) {
                $validator->errors()->add('lesson_block_id', 'This content is not currently available.');

                return;
            }

            $latest = $sessionLessonBlock->submissions()
                ->where('student_id', $this->user()->id)
                ->orderByDesc('attempt_number')
                ->first();

            // A draft is simply resumed — it doesn't need an attempts check,
            // since it hasn't consumed an attempt yet.
            if ($latest?->status === SubmissionStatus::Draft) {
                return;
            }

            // A grade is final — the tutor's decision stands regardless of
            // how many attempts the delivery configuration would otherwise allow.
            if ($latest?->status === SubmissionStatus::Graded) {
                $validator->errors()->add('lesson_block_id', 'This activity has already been graded and no longer accepts submissions.');

                return;
            }

            if (! $sessionLessonBlock->hasAttemptsRemainingFor($this->user()->id)) {
                $validator->errors()->add('lesson_block_id', 'You have used the maximum number of attempts for this activity.');
            }
        });
    }
}
