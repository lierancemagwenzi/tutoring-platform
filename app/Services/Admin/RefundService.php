<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Validation\ValidationException;

/**
 * Reverses the commercial effect of a paid transaction — admin-tracked
 * bookkeeping only, mirroring PayoutService's "track that it happened, not
 * a live gateway call" philosophy: this codebase has no PayFast API refund
 * integration, so the actual money movement back to the student happens
 * out-of-band via PayFast's merchant dashboard. FinancialTransaction's
 * core amounts (gross_amount/platform_fee_total/tutor_amount) are never
 * mutated — refund state is tracked via dedicated columns, the same
 * pattern already established by payout_status/paid_at/paid_by.
 */
class RefundService
{
    public function __construct(private readonly AdminActivityLogger $logger, private readonly PayoutService $payouts) {}

    public function refund(FinancialTransaction $transaction, User $actor, string $reason): FinancialTransaction
    {
        if ($transaction->refund_status === RefundStatus::Refunded) {
            throw ValidationException::withMessages(['refund' => 'This transaction has already been refunded.']);
        }

        $wasPaid = $transaction->payout_status === PayoutStatus::Paid;

        $transaction->update([
            'refund_status' => RefundStatus::Refunded,
            'refunded_at' => now(),
            'refunded_by' => $actor->id,
        ]);

        $transaction->order?->update(['status' => OrderStatus::Refunded]);

        if ($wasPaid) {
            // The money already left — flag for manual clawback rather than
            // silently rewriting history.
            $this->payouts->markAdjusted($transaction, $actor, "Refunded after payout: {$reason}");
        }

        $this->logger->log(
            $actor,
            'financial_transaction.refunded',
            $transaction,
            "Refunded {$transaction->currency} {$transaction->gross_amount} for tutor #{$transaction->tutor_profile_id}: {$reason}",
            ['reason' => $reason, 'was_paid' => $wasPaid],
        );

        $transaction->loadMissing('tutorProfile.user', 'student');

        $transaction->tutorProfile->user->notify(new UserNotification(
            type: 'financial_transaction.refunded',
            title: 'A booking was refunded',
            body: "A {$transaction->currency} {$transaction->gross_amount} transaction was refunded and removed from your payout.",
            url: '/tutor/earnings',
        ));

        $transaction->student->notify(new UserNotification(
            type: 'financial_transaction.refunded',
            title: 'Your payment was refunded',
            body: "Your payment of {$transaction->currency} {$transaction->gross_amount} has been refunded.",
            url: '/student/financial-transactions',
        ));

        return $transaction->fresh();
    }
}
