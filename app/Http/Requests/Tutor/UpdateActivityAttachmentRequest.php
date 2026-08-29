<?php

namespace App\Http\Requests\Tutor;

use App\Enums\LessonBlockStatus;
use App\Enums\MediaType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateActivityAttachmentRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const EXCLUDED_TYPES = ['video_youtube', 'video_vimeo'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('activityAttachment')->learningActivity->lesson->chapter->course->tutor_profile_id === $this->user()->tutorProfile?->id;
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:102400'],
            'status' => ['required', Rule::enum(LessonBlockStatus::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mediaType = MediaType::tryFrom((string) $this->input('media_type'));

            if (! $mediaType) {
                return;
            }

            if (! $this->hasFile('file') && ! $this->route('activityAttachment')->file_path) {
                $validator->errors()->add('file', 'A file is required.');

                return;
            }

            if ($this->hasFile('file')) {
                $extension = strtolower($this->file('file')->getClientOriginalExtension());

                if (! in_array($extension, $mediaType->acceptedMimes(), true)) {
                    $validator->errors()->add('file', 'The file does not match the selected attachment type.');
                }
            }
        });
    }
}
