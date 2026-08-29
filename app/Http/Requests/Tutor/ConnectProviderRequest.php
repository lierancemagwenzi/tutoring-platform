<?php

namespace App\Http\Requests\Tutor;

use App\Enums\ConnectedAccountProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConnectProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            if (! ConnectedAccountProvider::tryFrom((string) $this->route('provider'))) {
                $validator->errors()->add('provider', 'This provider is not supported.');
            }
        });
    }
}
