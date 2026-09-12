<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\BookingAccepted;
use App\Services\Commerce\OrderService;
use Illuminate\Support\Facades\DB;

/**
 * Accepting a booking request creates its commerce Order immediately —
 * capacity/ownership/pending-state are already validated by
 * AcceptBookingRequest before this ever runs, so this service only
 * orchestrates the Order creation + status transition.
 */
class BookingAcceptanceService
{
    public function __construct(private readonly OrderService $orders) {}

    public function accept(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $order = $this->orders->createForBooking($booking);

            $booking->update([
                'order_id' => $order->id,
                'status' => BookingStatus::AwaitingPayment,
            ]);

            $booking->loadMissing('student', 'tutorProfile.user');
            $booking->student->notify(new BookingAccepted(
                tutorName: trim("{$booking->tutorProfile->user->first_name} {$booking->tutorProfile->user->last_name}"),
                bookingId: $booking->id,
            ));

            return $booking->fresh();
        });
    }
}
