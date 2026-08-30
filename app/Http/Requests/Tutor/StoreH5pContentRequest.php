<?php

namespace App\Http\Requests\Tutor;

use App\Enums\TutorSubjectStatus;
use App\Models\TutorSubject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload sent by <h5p-editor>'s save() call when creating
 * new content. The nested "params" structure is arbitrary, semantics-driven
 * H5P content JSON that can't be meaningfully validated field-by-field here
 * — the H5P server is the authority on whether it's well-formed.
 *
 * grade_id/subject_id/curriculum_id mirror StoreCourseRequest's rules
 * exactly — a tutor may only classify content under a subject/grade they're
 * approved to teach.
 */
class StoreH5pContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tutorSubjectId = TutorSubject::query()
            ->where('tutor_profile_id', $this->user()->tutorProfile?->id)
            ->where('subject_id', $this->input('subject_id'))
            ->where('status', TutorSubjectStatus::Approved->value)
            ->value('id');

        return [
            'library' => ['required', 'string'],
            'params' => ['required', 'array'],
            'params.params' => ['required'],
            'params.metadata' => ['required', 'array'],
            'curriculum_id' => ['required', 'integer', Rule::exists('curricula', 'id')->where('is_active', true)],
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where('is_active', true),
                Rule::exists('tutor_subject_grades', 'grade_id')->where('tutor_subject_id', $tutorSubjectId),
            ],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('tutor_subjects', 'subject_id')->where(
                    fn ($query) => $query
                        ->where('tutor_profile_id', $this->user()->tutorProfile?->id)
                        ->where('status', TutorSubjectStatus::Approved->value),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.exists' => 'You can only classify content under a subject you teach that has been approved.',
            'grade_id.exists' => 'You are only approved to teach this subject for the grades assigned to it.',
        ];
    }
}
