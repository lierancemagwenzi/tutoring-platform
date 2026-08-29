<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Scope and target are immutable after creation — only the commission
     * figures can be changed. Create a new rule (with the old one
     * deactivated) to move an override to a different target.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0'],
            'provider_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'provider_fee_fixed' => ['nullable', 'numeric', 'min:0'],
            // Defaults to now (apply immediately) when omitted — see
            // FinancialRuleManagementService. A future date schedules the
            // change without touching the currently-effective rate.
            'effective_from' => ['nullable', 'date'],
        ];
    }
}
