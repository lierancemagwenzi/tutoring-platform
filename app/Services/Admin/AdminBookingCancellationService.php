<?php

namespace App\Services\Admin;

use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Models\Booking;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Validation\ValidationException;

/**
 * Admin-initiated cancellation of an already-paid booking — deliberately
 * separate from the pre-payment cancel/reject actions already available to
 * students/tutors (Api\Student\BookingController::cancel(),
 * Api\Tutor\BookingRequestController::reject()), which only ever run before
 * money has moved and so never need a refund. Cancellation windows and
 * refund-percentage policy are explicitly left to the business (see
 * revenue.docx Section 8) — this only builds the mechanism.
 */
class AdminBookingCancellationService
{
    public function __construct(private readonly AdminActivityLogger $logger, private readonly RefundService $refunds) {}

    public function cancel(Booking $booking, User $actor, string $reason): Booking
    {
        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::AwaitingPayment], true)) {
            throw ValidationException::withMessages(['booking' => 'Only a confirmed or awaiting-payment booking can be cancelled this way.']);
        }

        $booking->update(['status' => BookingStatus::Cancelled]);

        $transaction = FinancialTransaction::where('product_type', ProductType::TutoringServiceBooking->value)
            ->where('product_id', $booking->id)
            ->first();

        if ($transaction) {
            $this->refunds->refund($transaction, $actor, $reason);
        }

        $this->logger->log(
            $actor,
            'booking.cancelled',
            $booking,
            "Cancelled booking #{$booking->id}: {$reason}",
            ['reason' => $reason, 'refunded' => $transaction !== null],
        );

        if (! $transaction) {
            // No payment was ever captured for this booking — RefundService
            // already notifies both parties when it runs, so only notify
            // here for the no-payment path to avoid a duplicate message.
            $booking->loadMissing('student', 'tutorProfile.user');
            $booking->student->notify(new UserNotification(
                type: 'booking.cancelled',
                title: 'Booking cancelled',
                body: "An admin cancelled your booking: {$reason}",
                url: '/student/bookings',
            ));
            $booking->tutorProfile->user->notify(new UserNotification(
                type: 'booking.cancelled',
                title: 'Booking cancelled',
                body: "An admin cancelled a booking: {$reason}",
                url: '/tutor/booking-requests',
            ));
        }

        return $booking->fresh();
    }
}
