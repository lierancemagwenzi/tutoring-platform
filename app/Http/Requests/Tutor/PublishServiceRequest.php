<?php

namespace App\Http\Requests\Tutor;

use App\Models\Service;
use App\Services\Admin\MarketplaceEligibilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublishServiceRequest extends FormRequest
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
            $tutor = $this->user()->tutorProfile;
            $service = $this->route('service');
            $eligibility = app(MarketplaceEligibilityService::class);

            $errors = [
                ...$eligibility->tutorEligibilityErrors($tutor),
                ...$eligibility->subjectEligibilityErrors($tutor, $service->subject_id),
            ];

            foreach ($errors as $error) {
                $validator->errors()->add('eligibility', $error);
            }
        });
    }
}
