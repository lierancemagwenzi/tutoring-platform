<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Enums\Currency;
use App\Enums\SelfPacedDifficulty;
use App\Services\Admin\BankingEligibilityService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSelfPacedCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->tutorProfile !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'promo_description' => ['nullable', 'string', 'max:5000'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'difficulty' => ['nullable', Rule::enum(SelfPacedDifficulty::class)],
            'language' => ['nullable', 'string', 'max:100'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'learning_objectives' => ['nullable', 'array'],
            'learning_objectives.*' => ['string', 'max:500'],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*' => ['string', 'max:500'],
            'target_audience' => ['nullable', 'array'],
            'target_audience.*' => ['string', 'max:500'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $errors = app(BankingEligibilityService::class)->errorsFor($this->user()->tutorProfile);

            foreach ($errors as $error) {
                $validator->errors()->add('banking_details', $error);
            }
        });
    }
}
