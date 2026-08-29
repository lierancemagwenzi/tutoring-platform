<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\BankingEligibilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveTutorRequest extends FormRequest
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
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $errors = app(BankingEligibilityService::class)->errorsFor($this->route('user')?->tutorProfile);

            foreach ($errors as $error) {
                $validator->errors()->add('banking_details', $error);
            }
        });
    }
}
