<?php

namespace App\Http\Requests\Tutor;

use App\Enums\Currency;
use App\Enums\ServiceVisibility;
use App\Enums\TutorSubjectStatus;
use App\Models\Service;
use App\Models\TutorSubject;
use App\Services\Admin\PlatformSettingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $service = $this->route('service');

        if ($service instanceof Service) {
            return $service->tutor_profile_id === $this->user()->tutorProfile?->id;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('tutor_subjects', 'subject_id')->where(
                    fn ($query) => $query
                        ->where('tutor_profile_id', $this->user()->tutorProfile?->id)
                        ->where('status', TutorSubjectStatus::Approved->value),
                ),
            ],
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where('is_active', true),
                Rule::exists('tutor_subject_grades', 'grade_id')->where('tutor_subject_id', $tutorSubjectId),
            ],
            'service_category_id' => ['required', 'integer', Rule::exists('service_categories', 'id')->where('is_active', true)],
            'session_format_id' => ['required', 'integer', Rule::exists('session_formats', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0.01'],
            // ZAR is the only currency PayFast (the sole payment gateway) can
            // settle — see OrderService's currency checks.
            'currency' => ['required', Rule::in([Currency::ZAR->value])],
            'session_duration_minutes' => ['required', 'integer', 'min:1'],
            'sessions_included' => ['required', 'integer', 'min:1'],
            'validity_period_days' => ['required', 'integer', 'min:1'],
            'max_students_per_session' => ['required', 'integer', 'min:1'],
            'learning_resource_ids' => ['nullable', 'array'],
            'learning_resource_ids.*' => ['distinct', 'integer', Rule::exists('learning_resources', 'id')->where('is_active', true)],
            'assessment_type_ids' => ['nullable', 'array'],
            'assessment_type_ids.*' => ['distinct', 'integer', Rule::exists('assessment_types', 'id')->where('is_active', true)],
            'curriculum_ids' => ['nullable', 'array'],
            'curriculum_ids.*' => ['distinct', 'integer', Rule::exists('curricula', 'id')->where('is_active', true)],
            'visibility' => ['required', Rule::enum(ServiceVisibility::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.min' => 'Price must be greater than zero.',
            'subject_id.exists' => 'You can only create a service for a subject you teach that has been approved.',
            'grade_id.exists' => 'You are only approved to teach this subject for the grades assigned to it.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validatePriceLimits($validator);
        });
    }

    /**
     * Admin-configurable price floor/ceiling — disabled when null.
     */
    private function validatePriceLimits(Validator $validator): void
    {
        $price = $this->input('price');

        if (! is_numeric($price)) {
            return;
        }

        $settings = app(PlatformSettingService::class);
        $min = $settings->get('pricing.min_tutor_price');
        $max = $settings->get('pricing.max_tutor_price');

        if ($min !== null && $min !== '' && (float) $price < (float) $min) {
            $validator->errors()->add('price', "Price must be at least {$min}.");
        }

        if ($max !== null && $max !== '' && (float) $price > (float) $max) {
            $validator->errors()->add('price', "Price must not exceed {$max}.");
        }
    }
}
