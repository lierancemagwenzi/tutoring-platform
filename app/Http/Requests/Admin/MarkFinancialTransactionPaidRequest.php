<?php

namespace App\Http\Requests\Admin;

use App\Enums\PayoutStatus;
use App\Services\Admin\PayoutService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarkFinancialTransactionPaidRequest extends FormRequest
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
            $transaction = $this->route('financialTransaction');

            if ($transaction->payout_status === PayoutStatus::Paid) {
                $validator->errors()->add('payout', 'This earning has already been marked as paid.');

                return;
            }

            if (! app(PayoutService::class)->isEligible($transaction)) {
                $validator->errors()->add('payout', 'This booking has not been completed yet — it cannot be paid out.');
            }
        });
    }
}
