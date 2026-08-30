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
 * Validates the payload sent by <h5p-editor>'s save() call when creating
 * new content. The nested "params" structure is arbitrary, semantics-driven
 * H5P content JSON that can't be meaningfully validated field-by-field here
 * — the H5P server is the authority on whether it's well-formed.
 *
 * grade_id/subject_id/curriculum_id normally mirror StoreCourseRequest's
 * rules exactly — a tutor may only classify content under a subject/grade
 * they're approved to teach. But when creating content for a specific,
 * already-owned lesson block (lesson_block_id, set by H5pEditor.vue when
 * reached via H5pManager.vue's "Create New") or self-paced course
 * (self_paced_course_id, same idea via ActivityEditor.vue's H5P activity
 * type), that record's own classification is the authority instead: it may
 * no longer intersect with the tutor's *current* approved-subjects list
 * (e.g. an approval was revoked since), but its existence already proves it
 * was legitimate — and H5pBlockHandler::rules() /
 * StoreSelfPacedActivityRequest::withValidator() will reject attaching
 * anything that doesn't match it exactly anyway, so anything else would be
 * unusable here regardless. Self-Paced courses have no curriculum, so that
 * anchor only constrains grade_id/subject_id — curriculum_id keeps its
 * normal free-pick validation in that branch.
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
     * Same idea as ownedLessonBlockCourse(), for a Self-Paced course.
     */
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
