<?php

namespace App\Http\Requests\Admin;

use App\Enums\PayoutStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayoutStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in([PayoutStatus::Eligible->value, PayoutStatus::Processing->value, PayoutStatus::OnHold->value])],
        ];
    }
}
