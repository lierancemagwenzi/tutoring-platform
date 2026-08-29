<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Enums\MediaType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSelfPacedActivityAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedActivity')->module->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'media_type' => ['required', Rule::enum(MediaType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:102400'],
            'external_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mediaType = MediaType::tryFrom((string) $this->input('media_type'));

            if ($mediaType?->isExternal()) {
                if (! $this->filled('external_url')) {
                    $validator->errors()->add('external_url', 'A URL is required for this media type.');
                }

                return;
            }

            if (! $this->hasFile('file')) {
                $validator->errors()->add('file', 'A file is required for this media type.');

                return;
            }

            $extension = strtolower($this->file('file')->getClientOriginalExtension());
            if ($mediaType && ! in_array($extension, $mediaType->acceptedMimes(), true)) {
                $validator->errors()->add('file', 'The file does not match the selected media type.');
            }
        });
    }
}
