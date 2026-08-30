<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Enums\SelfPacedActivityType;
use App\Models\H5pContentClassification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSelfPacedActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedModule')->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SelfPacedActivityType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'required' => ['sometimes', 'boolean'],
            'content' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * An h5p activity's content.h5p_content_id must reference content this
     * tutor owns (see App\Models\H5pContentClassification) and that's
     * classified under this module's course's exact Subject/Grade — Self-
     * Paced courses have no curriculum to also check (unlike Tutor-Led's
     * H5pBlockHandler::rules(), the closest equivalent for lesson blocks).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (SelfPacedActivityType::tryFrom((string) $this->input('type')) !== SelfPacedActivityType::H5p) {
                return;
            }

            $id = $this->input('content.h5p_content_id');
            if (blank($id)) {
                $validator->errors()->add('content', 'The content.h5p_content_id field is required for H5P activities.');

                return;
            }

            $course = $this->route('selfPacedModule')->course;
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
