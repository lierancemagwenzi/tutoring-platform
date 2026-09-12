<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Models\Booking;
use App\Models\Order;
use App\Notifications\BookingPaid;
use App\Notifications\UserNotification;

/**
 * Confirms every tutoring-service-booking line item on a paid order by
 * marking the booking Confirmed. Mirrors EnrollmentService::activate()'s
 * self-filtering pattern so PayFastItnHandler can call this unconditionally
 * on any paid order. Idempotent — safe to call more than once for the same
 * order (the ITN handler's own idempotency guard should prevent that in
 * practice, but the "already Confirmed" check here is the backstop).
 *
 * Payment no longer auto-creates a TeachingSession — a Confirmed booking
 * represents a purchased package of N sessions (Service.sessions_included);
 * the tutor schedules each one individually via SessionSchedulingService.
 */
class BookingConfirmationService
{
    public function confirm(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_type !== ProductType::TutoringServiceBooking->value) {
                continue;
            }

            $booking = Booking::find($item->product_id);

            if (! $booking || $booking->status === BookingStatus::Confirmed) {
                continue;
            }

            $booking->update(['status' => BookingStatus::Confirmed]);

            $booking->loadMissing('student', 'tutorProfile.user', 'service.subject');
            $booking->student->notify(new UserNotification(
                type: 'booking.confirmed',
                title: 'Booking confirmed',
                body: "Your booking with {$booking->tutorProfile->user->first_name} was confirmed.",
                url: "/student/bookings/{$booking->id}",
            ));
            $booking->tutorProfile->user->notify(new BookingPaid(
                studentName: trim("{$booking->student->first_name} {$booking->student->last_name}"),
                subjectName: $booking->service->subject->name,
                bookingId: $booking->id,
            ));
        }
    }
}
