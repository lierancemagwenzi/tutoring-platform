<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderSelfPacedActivityAttachmentsRequest extends FormRequest
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
            'attachment_ids' => ['required', 'array'],
            'attachment_ids.*' => [
                'integer',
                Rule::exists('self_paced_activity_attachments', 'id')->where('self_paced_activity_id', $this->route('selfPacedActivity')->id),
            ],
        ];
    }
}
