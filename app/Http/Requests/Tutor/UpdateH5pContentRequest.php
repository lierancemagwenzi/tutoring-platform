<?php

namespace App\Http\Requests\Tutor;

use App\Enums\TutorSubjectStatus;
use App\Models\Course;
use App\Models\LessonBlock;
use App\Models\SelfPacedCourse;
use App\Models\TutorSubject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * See StoreH5pContentRequest's docblock — grade_id/subject_id/curriculum_id
 * follow the same "lesson_block_id or self_paced_course_id anchors it to
 * that record's own classification" exception to the normal
 * approved-subjects rule.
 */
class UpdateH5pContentRequest extends FormRequest
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
            'self_paced_course_id' => ['nullable', 'integer'],
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

        $selfPacedCourse = $this->ownedSelfPacedCourse();
        if ($selfPacedCourse) {
            return [
                ...$baseRules,
                'grade_id' => ['required', Rule::in([$selfPacedCourse->grade_id])],
                'subject_id' => ['required', Rule::in([$selfPacedCourse->subject_id])],
                'curriculum_id' => ['required', 'integer', Rule::exists('curricula', 'id')->where('is_active', true)],
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

    protected function ownedLessonBlockCourse(): ?Course
    {
        $lessonBlockId = $this->input('lesson_block_id');
        if (! $lessonBlockId) {
            return null;
        }

        $course = LessonBlock::find($lessonBlockId)?->lesson?->chapter?->course;

        return $course && $course->tutor_profile_id === $this->user()->tutorProfile?->id ? $course : null;
    }

    protected function ownedSelfPacedCourse(): ?SelfPacedCourse
    {
        $selfPacedCourseId = $this->input('self_paced_course_id');
        if (! $selfPacedCourseId) {
            return null;
        }

        $course = SelfPacedCourse::find($selfPacedCourseId);

        return $course && $course->tutor_profile_id === $this->user()->tutorProfile?->id ? $course : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.exists' => 'You can only classify content under a subject you teach that has been approved.',
            'subject_id.in' => "This must match the course's subject.",
            'grade_id.exists' => 'You are only approved to teach this subject for the grades assigned to it.',
            'grade_id.in' => "This must match the course's grade.",
            'curriculum_id.in' => "This must match the lesson's course curriculum.",
        ];
    }
}
