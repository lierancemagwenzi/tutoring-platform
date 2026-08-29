<?php

namespace App\Services\Commerce;

use App\Enums\FinancialFeeType;
use App\Enums\FinancialSplitRecipientType;
use App\Enums\PayoutStatus;
use App\Models\FinancialTransaction;
use App\Models\OrderItem;
use App\Models\Payment;

/**
 * Computes and permanently records the commission split for a confirmed
 * payment — called once from PayFastItnHandler right after the payment is
 * marked successful, inside the same DB transaction. The resulting
 * FinancialTransaction (and its fee/split rows) is never recomputed or
 * updated afterwards: it is the historical record of what was true at the
 * moment the money was paid, regardless of later FinancialRule changes.
 */
class CommissionSnapshotService
{
    public function __construct(private readonly FinancialRuleResolverService $rules) {}

    public function record(Payment $payment): void
    {
        $order = $payment->order()->with('items')->first();

        foreach ($order->items as $item) {
            $this->recordForItem($payment, $item);
        }
    }

    private function recordForItem(Payment $payment, OrderItem $item): void
    {
        $rule = $this->rules->resolveFor($item);
        $tutorProfile = $this->rules->tutorProfileFor($item);

        $gross = (float) $item->total;
        $percentageAmount = round($gross * (float) $rule->percentage / 100, 2);
        $fixedFee = (float) $rule->fixed_fee;
        $providerFeeAmount = round(
            (float) ($rule->provider_fee_fixed ?? 0) + $gross * (float) ($rule->provider_fee_percentage ?? 0) / 100,
            2,
        );
        $platformFeeTotal = round($percentageAmount + $fixedFee + $providerFeeAmount, 2);
        $tutorAmount = round($gross - $platformFeeTotal, 2);

        $transaction = FinancialTransaction::create([
            'payment_id' => $payment->id,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'tutor_profile_id' => $tutorProfile->id,
            'student_id' => $payment->order->student_id,
            'product_type' => $item->product_type,
            'product_id' => $item->product_id,
            'gross_amount' => $gross,
            'currency' => $payment->currency->value,
            'financial_rule_id' => $rule->id,
            'platform_fee_total' => $platformFeeTotal,
            'tutor_amount' => $tutorAmount,
            // Explicit rather than relying on the DB column default — a
            // value omitted from create() leaves the in-memory model
            // attribute unset until a refresh, which bit us for
            // FinancialRule.currency earlier this session.
            'payout_status' => PayoutStatus::Pending,
        ]);

        $fees = [
            [FinancialFeeType::PlatformCommission, (float) $rule->percentage, $percentageAmount],
            [FinancialFeeType::PlatformFixed, null, $fixedFee],
            [FinancialFeeType::ProviderFee, $rule->provider_fee_percentage !== null ? (float) $rule->provider_fee_percentage : null, $providerFeeAmount],
        ];

        foreach ($fees as [$feeType, $percentage, $amount]) {
            if ($amount <= 0 && $percentage === null) {
                continue;
            }

            $transaction->fees()->create([
                'fee_type' => $feeType,
                'percentage' => $percentage,
                'amount' => $amount,
            ]);
        }

        $transaction->splits()->createMany([
            ['recipient_type' => FinancialSplitRecipientType::Platform, 'recipient_id' => null, 'amount' => $platformFeeTotal],
            ['recipient_type' => FinancialSplitRecipientType::Tutor, 'recipient_id' => $tutorProfile->id, 'amount' => $tutorAmount],
        ]);
    }
}
