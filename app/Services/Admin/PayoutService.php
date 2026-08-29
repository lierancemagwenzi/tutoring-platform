<?php

namespace App\Services\Admin;

use App\Enums\BookingStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductType;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\UserNotification;

/**
 * Marks a tutor's earning as paid out — a manual admin action, not a
 * payment-gateway integration. The platform pays tutors via real-world
 * bank transfer (see TutorBankAccount); this only tracks that it happened.
 */
class PayoutService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    /**
     * Course-type earnings have no service-delivery milestone to wait for
     * (the content is available the instant the purchase is confirmed), so
     * they're payable immediately. Booking-type earnings are only payable
     * once every session in the purchased package has been delivered —
     * see SessionSchedulingService::maybeCompleteBooking().
     */
    public function isEligible(FinancialTransaction $transaction): bool
    {
        if ($transaction->refund_status === RefundStatus::Refunded) {
            return false;
        }

        if ($transaction->product_type !== ProductType::TutoringServiceBooking->value) {
            return true;
        }

        $booking = Booking::find($transaction->product_id);

        return $booking !== null && $booking->status === BookingStatus::Completed;
    }

    public function markPaid(FinancialTransaction $transaction, User $actor): FinancialTransaction
    {
        $transaction->update([
            'payout_status' => PayoutStatus::Paid,
            'paid_at' => now(),
            'paid_by' => $actor->id,
        ]);

        $this->logger->log(
            $actor,
            'financial_transaction.paid',
            $transaction,
            "Marked earning of {$transaction->currency} {$transaction->tutor_amount} as paid for tutor #{$transaction->tutor_profile_id}.",
            ['tutor_profile_id' => $transaction->tutor_profile_id, 'amount' => (string) $transaction->tutor_amount],
        );

        $transaction->loadMissing('tutorProfile.user');
        $transaction->tutorProfile->user->notify(new UserNotification(
            type: 'payout.paid',
            title: 'Payout sent',
            body: "Your earning of {$transaction->currency} {$transaction->tutor_amount} has been marked as paid.",
            url: '/tutor/earnings',
        ));

        return $transaction;
    }

    /**
     * Move a transaction between the intermediate payout-pipeline statuses
     * (Eligible, Processing, On Hold) — everything short of actually paying
     * it out (markPaid) or reversing it (RefundService::refund(), which is
     * the only path to Adjusted). Admin-facing status changes only; no
     * business rule enforces a strict order between these three, since
     * payout processing is a manual admin workflow, not an automated one.
     */
    public function updateStatus(FinancialTransaction $transaction, PayoutStatus $status, User $actor): FinancialTransaction
    {
        abort_if(! in_array($status, [PayoutStatus::Eligible, PayoutStatus::Processing, PayoutStatus::OnHold], true), 422, 'Invalid payout status transition.');
        abort_if($transaction->payout_status === PayoutStatus::Paid, 422, 'This earning has already been paid out.');

        $transaction->update(['payout_status' => $status]);

        $this->logger->log(
            $actor,
            'financial_transaction.payout_status_updated',
            $transaction,
            "Moved payout status for tutor #{$transaction->tutor_profile_id}'s earning to {$status->value}.",
            ['status' => $status->value],
        );

        return $transaction;
    }

    /**
     * Flags a transaction as needing manual reconciliation — set only as a
     * side effect of RefundService::refund() when a Paid transaction is
     * refunded after the money has already gone out, since the original
     * FinancialTransaction amounts are never mutated (see RefundService).
     */
    public function markAdjusted(FinancialTransaction $transaction, User $actor, string $reason): FinancialTransaction
    {
        $transaction->update(['payout_status' => PayoutStatus::Adjusted]);

        $this->logger->log(
            $actor,
            'financial_transaction.payout_adjusted',
            $transaction,
            "Flagged tutor #{$transaction->tutor_profile_id}'s earning as adjusted: {$reason}",
            ['reason' => $reason],
        );

        return $transaction;
    }
}
