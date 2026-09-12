<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Models\Booking;
use App\Models\Order;
use App\Notifications\BookingPaid;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Confirms every tutoring-service-booking line item on a paid order by
 * marking the booking Confirmed. Mirrors EnrollmentService::activate()'s
 * self-filtering pattern so PayFastItnHandler can call this unconditionally
 * on any paid order. Idempotent — safe to call more than once for the same
 * order (the ITN handler's own idempotency guard should prevent that in
 * practice, but the "already Confirmed" check here is the backstop).
 *
 * Also auto-schedules the first TeachingSession from the date/time the
 * student originally requested — see scheduleFirstSession() — so that
 * selection isn't discarded once payment lands. Any further sessions in
 * the purchased package (Service.sessions_included) are still scheduled
 * individually by the tutor via SessionSchedulingService.
 */
class BookingConfirmationService
{
    public function __construct(private readonly SessionSchedulingService $sessions) {}

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

            $this->scheduleFirstSession($booking);

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

    /**
     * Books the student's originally requested date/time as the first
     * session of the package, rather than leaving it stranded on the
     * Booking row while the tutor schedules from scratch. This can
     * legitimately fail — the tutor's availability may have changed, or
     * (for a group class) the slot may have filled up — between the
     * request and payment clearing, so a failure here is swallowed: the
     * booking stays Confirmed and the tutor schedules it manually instead,
     * exactly as before this method existed.
     */
    private function scheduleFirstSession(Booking $booking): void
    {
        try {
            $this->sessions->scheduleSession($booking, [
                'date' => $booking->date->format('Y-m-d'),
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
            ]);
        } catch (RuntimeException $e) {
            Log::warning("Could not auto-schedule the requested session for booking #{$booking->id}: {$e->getMessage()}");
        }
    }
}
