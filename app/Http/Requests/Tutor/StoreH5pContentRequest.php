<?php

namespace App\Http\Requests\Tutor;

use App\Enums\TutorSubjectStatus;
use App\Models\Course;
use App\Models\LessonBlock;
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
 * grade_id/subject_id/curriculum_id normally mirror StoreCourseRequest's
 * rules exactly — a tutor may only classify content under a subject/grade
 * they're approved to teach. But when creating content for a specific,
 * already-owned lesson block (lesson_block_id, set by H5pEditor.vue when
 * reached via H5pManager.vue's "Create New"), that block's ancestor Course
 * is the authority instead: the Course's own classification may no longer
 * intersect with the tutor's *current* approved-subjects list (e.g. an
 * approval was revoked after the Course was created), but the Course's
 * existence already proves it was legitimate — and H5pBlockHandler::rules()
 * will reject attaching anything that doesn't match it exactly anyway, so
 * anything else would be unusable here regardless.
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
        $baseRules = [
            'library' => ['required', 'string'],
            'params' => ['required', 'array'],
            'params.params' => ['required'],
            'params.metadata' => ['required', 'array'],
            'lesson_block_id' => ['nullable', 'integer'],
        ];

        $course = $this->ownedLessonBlockCourse();

        if ($course) {
            return [
                ...$baseRules,
                'grade_id' => ['required', Rule::in([$course->grade_id])],
                'subject_id' => ['required', Rule::in([$course->subject_id])],
                'curriculum_id' => ['required', Rule::in([$course->curriculum_id])],
            ];
        }

        $tutorSubjectId = TutorSubject::query()
            ->where('tutor_profile_id', $this->user()->tutorProfile?->id)
            ->where('subject_id', $this->input('subject_id'))
            ->where('status', TutorSubjectStatus::Approved->value)
            ->value('id');

        return [
            ...$baseRules,
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
     * The target lesson block's ancestor Course, only if lesson_block_id was
     * given AND it belongs to the requesting tutor — an unowned or missing
     * id is treated exactly like no lesson_block_id at all, falling back to
     * the approved-subjects rules below rather than exposing anything about
     * another tutor's course.
     */
    protected function ownedLessonBlockCourse(): ?Course
    {
        $lessonBlockId = $this->input('lesson_block_id');
        if (! $lessonBlockId) {
            return null;
        }

        $course = LessonBlock::find($lessonBlockId)?->lesson?->chapter?->course;

        return $course && $course->tutor_profile_id === $this->user()->tutorProfile?->id ? $course : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.exists' => 'You can only classify content under a subject you teach that has been approved.',
            'subject_id.in' => "This must match the lesson's course subject.",
            'grade_id.exists' => 'You are only approved to teach this subject for the grades assigned to it.',
            'grade_id.in' => "This must match the lesson's course grade.",
            'curriculum_id.in' => "This must match the lesson's course curriculum.",
        ];
    }
}
