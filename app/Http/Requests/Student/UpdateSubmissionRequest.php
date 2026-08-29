<?php

namespace App\Http\Requests\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only the owning student may edit their own submission, and only
     * while it's still a draft — once submitted it's locked.
     */
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission->student_id === $this->user()->id && $submission->isEditable();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submission_text_html' => ['nullable', 'string'],
            'submission_text_json' => ['nullable', 'array'],
        ];
    }
}
