<?php

namespace App\Http\Requests\Student;

use App\Enums\SubmissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('submission')->student_id === $this->user()->id;
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
     * Re-checks availability at submit time (not just at draft-start time —
     * time may have passed), and requires whatever content the Lesson
     * Block's own Submission Type demands.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $submission = $this->route('submission');

            if (! $submission->isEditable()) {
                $validator->errors()->add('status', 'This submission has already been submitted.');

                return;
            }

            $sessionLessonBlock = $submission->sessionLessonBlock;

            if (! $sessionLessonBlock->isAvailable()) {
                $validator->errors()->add('status', 'This content is not currently available.');

                return;
            }

            $submissionType = $sessionLessonBlock->lessonBlock->learningActivity()?->submission_type;

            $hasText = filled($submission->submission_text['html'] ?? null);
            $hasFiles = $submission->studentAttachments()->exists();

            if (in_array($submissionType, [SubmissionType::Text, SubmissionType::TextAndFile], true) && ! $hasText) {
                $validator->errors()->add('submission_text_html', 'Submission text is required.');
            }

            if (in_array($submissionType, [SubmissionType::FileUpload, SubmissionType::TextAndFile], true) && ! $hasFiles) {
                $validator->errors()->add('attachments', 'At least one file attachment is required.');
            }
        });
    }
}
