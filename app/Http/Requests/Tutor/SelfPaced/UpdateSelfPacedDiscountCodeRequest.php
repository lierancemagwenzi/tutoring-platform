<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Enums\SelfPacedDiscountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSelfPacedDiscountCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedDiscountCode')->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('self_paced_discount_codes', 'code')
                    ->where('self_paced_course_id', $this->route('selfPacedDiscountCode')->self_paced_course_id)
                    ->ignore($this->route('selfPacedDiscountCode')->id),
            ],
            'discount_type' => ['sometimes', Rule::enum(SelfPacedDiscountType::class)],
            'discount_value' => ['sometimes', 'numeric', 'min:0.01'],
            'max_redemptions' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('discount_type', $this->route('selfPacedDiscountCode')->discount_type->value);

            if ($this->has('discount_value') && $type === SelfPacedDiscountType::Percentage->value && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'A percentage discount cannot exceed 100.');
            }
        });
    }
}
