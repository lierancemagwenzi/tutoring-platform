<?php

namespace App\Http\Requests\Tutor;

use App\Enums\TutorSubjectStatus;
use App\Models\TutorSubject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Uploading a .h5p package is the secondary, import-only content workflow —
 * authoring directly in the H5P editor is primary. Imported content still
 * needs an owner and Subject/Grade/Curriculum classification, same as
 * content created via the editor (see StoreH5pContentRequest).
 */
class ImportH5pContentRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:51200', 'extensions:h5p'],
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
}
