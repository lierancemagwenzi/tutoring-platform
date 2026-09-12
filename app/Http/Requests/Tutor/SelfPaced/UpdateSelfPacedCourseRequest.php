<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Enums\Currency;
use App\Enums\SelfPacedCourseVisibility;
use App\Enums\SelfPacedDifficulty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSelfPacedCourseRequest extends FormRequest
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
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'promo_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'subject_id' => ['sometimes', 'nullable', 'integer', 'exists:subjects,id'],
            'grade_id' => ['sometimes', 'nullable', 'integer', 'exists:grades,id'],
            'service_category_id' => ['sometimes', 'nullable', 'integer', 'exists:service_categories,id'],
            'difficulty' => ['sometimes', 'nullable', Rule::enum(SelfPacedDifficulty::class)],
            'language' => ['sometimes', 'nullable', 'string', 'max:100'],
            'estimated_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'learning_objectives' => ['sometimes', 'nullable', 'array'],
            'learning_objectives.*' => ['string', 'max:500'],
            'prerequisites' => ['sometimes', 'nullable', 'array'],
            'prerequisites.*' => ['string', 'max:500'],
            'target_audience' => ['sometimes', 'nullable', 'array'],
            'target_audience.*' => ['string', 'max:500'],
            'visibility' => ['sometimes', Rule::enum(SelfPacedCourseVisibility::class)],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            // ZAR is the only currency PayFast (the sole payment gateway) can
            // settle — see OrderService's currency checks.
            'currency' => ['sometimes', 'nullable', Rule::in([Currency::ZAR->value])],
            'thumbnail' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'promo_video' => ['sometimes', 'nullable', 'file', 'mimes:mp4,mov,webm', 'max:102400'],
        ];
    }
}
