<?php

namespace App\Http\Requests\Student;

use App\Enums\SelfPacedCourseStatus;
use App\Enums\SelfPacedCourseVisibility;
use App\Models\SelfPacedCourse;
use App\Services\Commerce\EnrollmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Any authenticated non-tutor user is implicitly "the student" —
        // there is no dedicated 'student' middleware alias in this app.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_offering_id' => ['required', 'integer', 'exists:self_paced_courses,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $course = SelfPacedCourse::find($this->input('course_offering_id'));

            if (! $course) {
                return;
            }

            $purchasableVisibility = [
                SelfPacedCourseVisibility::PublicVisibility,
                SelfPacedCourseVisibility::Unlisted,
            ];

            if ($course->status !== SelfPacedCourseStatus::Published || ! in_array($course->visibility, $purchasableVisibility, true)) {
                $validator->errors()->add('course_offering_id', 'This course is not available for purchase.');

                return;
            }

            if (app(EnrollmentService::class)->hasAccess($this->user(), $course)) {
                $validator->errors()->add('course_offering_id', 'You already own this course.');
            }
        });
    }
}
