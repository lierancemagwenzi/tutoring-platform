<?php

namespace App\Http\Requests\Tutor;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignSessionLessonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('session')->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Lesson ownership (does this tutor's course actually contain this
     * lesson) is checked in the controller, matching how ownership chains
     * are checked everywhere else in this codebase rather than embedded
     * into a rule closure here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lesson_id' => [
                'required',
                'integer',
                'exists:lessons,id',
                Rule::unique('session_lessons', 'lesson_id')->where('teaching_session_id', $this->route('session')->id),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * A session's service defines the subject/grade/curriculum its students
     * actually paid for, so only lessons whose course aligns with that
     * service may be assigned — otherwise students would see an "assigned"
     * lesson they're not eligible to open.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('lesson_id')) {
                return;
            }

            $lesson = Lesson::find($this->input('lesson_id'));

            if (! $lesson) {
                return;
            }

            if ($lesson->status !== LessonStatus::Published) {
                $validator->errors()->add('lesson_id', 'Only published lessons can be assigned to a session.');

                return;
            }

            $service = $this->route('session')->service;

            if ($service && ! $lesson->chapter->course->matchesService($service)) {
                $validator->errors()->add(
                    'lesson_id',
                    "This lesson's subject, grade, and curriculum don't match this session's service.",
                );
            }
        });
    }
}
