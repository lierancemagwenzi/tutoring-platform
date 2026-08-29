<?php

namespace App\Http\Requests\Student;

use App\Enums\MediaType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubmissionAttachmentRequest extends FormRequest
{
    /**
     * Media types a submission attachment may not be (attachments here are
     * always uploaded files, never an external URL).
     *
     * @var list<string>
     */
    private const EXCLUDED_TYPES = ['video_youtube', 'video_vimeo', 'video_upload'];

    /**
     * Determine if the user is authorized to make this request.
     *
     * Only while the submission is still a draft — once submitted, its
     * attachments are locked along with everything else.
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
            'media_type' => [
                'required',
                Rule::in(array_diff(array_column(MediaType::cases(), 'value'), self::EXCLUDED_TYPES)),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:102400'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mediaType = MediaType::tryFrom((string) $this->input('media_type'));

            if (! $mediaType || ! $this->hasFile('file')) {
                return;
            }

            $extension = strtolower($this->file('file')->getClientOriginalExtension());

            if (! in_array($extension, $mediaType->acceptedMimes(), true)) {
                $validator->errors()->add('file', 'The file does not match the selected attachment type.');
            }
        });
    }
}
