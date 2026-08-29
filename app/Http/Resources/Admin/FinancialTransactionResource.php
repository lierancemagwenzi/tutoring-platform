<?php

namespace App\Http\Resources\Admin;

use App\Enums\PayoutStatus;
use App\Services\Admin\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
{
    /**
     * Statuses a transaction can still be marked Paid from — everything
     * except Paid itself (already done) and Adjusted (needs manual
     * reconciliation first, see RefundService).
     *
     * @var list<PayoutStatus>
     */
    private const PAYABLE_FROM = [PayoutStatus::Pending, PayoutStatus::Eligible, PayoutStatus::Processing, PayoutStatus::OnHold];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tutor' => [
                'id' => $this->tutorProfile->id,
                'name' => $this->tutorProfile->display_name,
            ],
            'student' => [
                'id' => $this->student->id,
                'name' => trim("{$this->student->first_name} {$this->student->last_name}"),
            ],
            'product_type' => $this->product_type,
            'product_id' => $this->product_id,
            // Native decimal:2 strings, not float-cast — see FinancialRuleResource for why.
            'gross_amount' => $this->gross_amount,
            'platform_fee_total' => $this->platform_fee_total,
            'tutor_amount' => $this->tutor_amount,
            'currency' => $this->currency,
            'rule_id' => $this->financial_rule_id,
            'payout_status' => $this->payout_status->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refund_status' => $this->refund_status->value,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'eligible_for_payout' => in_array($this->payout_status, self::PAYABLE_FROM, true) && app(PayoutService::class)->isEligible($this->resource),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
