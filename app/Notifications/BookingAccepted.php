<?php

namespace App\Notifications;

use App\Mail\BookingAcceptedMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to a student when a tutor accepts their booking request — see
 * BookingAcceptanceService::accept(). The tutor-facing equivalent for
 * payment is BookingPaid.
 */
class BookingAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tutorName,
        private readonly int $bookingId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): BookingAcceptedMail
    {
        return (new BookingAcceptedMail(
            studentFirstName: $notifiable->first_name,
            tutorName: $this->tutorName,
            bookingUrl: rtrim(config('app.url'), '/')."/student/bookings/{$this->bookingId}",
        ))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'booking.accepted',
            'title' => 'Booking accepted',
            'body' => "{$this->tutorName} accepted your booking request. Complete payment to confirm it.",
            'url' => "/student/bookings/{$this->bookingId}",
        ];
    }
}
