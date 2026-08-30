<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Enums\SelfPacedActivityType;
use App\Models\H5pContentClassification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSelfPacedActivityRequest extends FormRequest
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
     * Type is intentionally not editable — changing an activity's type
     * after content/attachments exist would orphan them; delete and
     * re-create instead.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'required' => ['sometimes', 'boolean'],
            'content' => ['sometimes', 'nullable', 'array'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * See StoreSelfPacedActivityRequest::withValidator() — same check, but
     * keyed off the existing activity's (immutable) type rather than an
     * incoming `type` field, and only when `content` is actually part of
     * this update.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $activity = $this->route('selfPacedActivity');

            if ($activity->type !== SelfPacedActivityType::H5p || ! $this->has('content')) {
                return;
            }

            $id = $this->input('content.h5p_content_id');
            if (blank($id)) {
                $validator->errors()->add('content', 'The content.h5p_content_id field is required for H5P activities.');

                return;
            }

            $course = $activity->module->course;
            $classification = H5pContentClassification::query()
                ->where('h5p_content_id', $id)
                ->where('tutor_profile_id', $this->user()->tutorProfile?->id)
                ->first();

            $matches = $classification
                && $classification->grade_id === $course->grade_id
                && $classification->subject_id === $course->subject_id;

            if (! $matches) {
                $validator->errors()->add('content', "The selected H5P content must match this course's subject and grade.");
            }
        });
    }
}
