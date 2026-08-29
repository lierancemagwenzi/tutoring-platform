<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Services\Admin\MarketplaceEligibilityService;
use App\Services\SelfPaced\SelfPacedPublishingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublishSelfPacedCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedCourse')->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $course = $this->route('selfPacedCourse');
            $errors = app(SelfPacedPublishingService::class)->errors($course);

            foreach ($errors as $error) {
                $validator->errors()->add('course', $error);
            }

            $tutor = $this->user()->tutorProfile;
            $eligibility = app(MarketplaceEligibilityService::class);

            $eligibilityErrors = [
                ...$eligibility->tutorEligibilityErrors($tutor),
                ...$eligibility->subjectEligibilityErrors($tutor, $course->subject_id),
            ];

            foreach ($eligibilityErrors as $error) {
                $validator->errors()->add('eligibility', $error);
            }
        });
    }
}
