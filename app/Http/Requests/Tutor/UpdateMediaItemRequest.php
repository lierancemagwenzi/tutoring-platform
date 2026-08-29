<?php

namespace App\Http\Requests\Tutor;

use App\Enums\LessonBlockStatus;
use App\Enums\MediaType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMediaItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('mediaItem')->lessonBlock->lesson->chapter->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'media_type' => ['required', Rule::in(array_column(MediaType::cases(), 'value'))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:102400'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
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

            $existing = $this->route('mediaItem');

            if ($mediaType->isExternal()) {
                if (! $this->filled('external_url') && ! $existing->external_url) {
                    $validator->errors()->add('external_url', 'An external URL is required for this media type.');
                }

                return;
            }

            if (! $this->hasFile('file') && ! $existing->file_path) {
                $validator->errors()->add('file', 'A file is required for this media type.');

                return;
            }

            if ($this->hasFile('file')) {
                $extension = strtolower($this->file('file')->getClientOriginalExtension());

                if (! in_array($extension, $mediaType->acceptedMimes(), true)) {
                    $validator->errors()->add('file', 'The file does not match the selected media type.');
                }
            }
        });
    }
}
