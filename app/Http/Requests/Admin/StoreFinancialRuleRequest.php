<?php

namespace App\Http\Requests\Admin;

use App\Models\FinancialRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFinancialRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'in:tutor,service,course'],
            'tutor_profile_id' => ['required_if:scope,tutor', 'prohibited_unless:scope,tutor', 'integer', 'exists:tutor_profiles,id'],
            'service_id' => ['required_if:scope,service', 'prohibited_unless:scope,service', 'integer', 'exists:services,id'],
            'self_paced_course_id' => ['required_if:scope,course', 'prohibited_unless:scope,course', 'integer', 'exists:self_paced_courses,id'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0'],
            'provider_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'provider_fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $scope = $this->input('scope');
            $targetKey = match ($scope) {
                'tutor' => 'tutor_profile_id',
                'service' => 'service_id',
                'course' => 'self_paced_course_id',
                default => null,
            };

            if ($targetKey === null || ! $this->filled($targetKey)) {
                return;
            }

            $exists = FinancialRule::query()
                ->where('scope', $scope)
                ->where($targetKey, $this->input($targetKey))
                ->exists();

            if ($exists) {
                $validator->errors()->add($targetKey, 'This tutor/service/course already has a commission rule. Edit the existing one instead.');
            }
        });
    }
}
