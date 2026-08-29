<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSelfPacedActivityAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedActivityAttachment')->activity->module->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file' => ['sometimes', 'nullable', 'file', 'max:102400'],
            'external_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
