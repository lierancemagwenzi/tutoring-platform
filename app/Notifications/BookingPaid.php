<?php

namespace App\Notifications;

use App\Mail\BookingPaidMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to a tutor when a student's booking payment clears and the booking
 * is confirmed — see BookingConfirmationService::confirm(). The
 * student-facing equivalent for acceptance is BookingAccepted.
 */
class BookingPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $studentName,
        private readonly string $subjectName,
        private readonly int $bookingId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): BookingPaidMail
    {
        return (new BookingPaidMail(
            tutorFirstName: $notifiable->first_name,
            studentName: $this->studentName,
            subjectName: $this->subjectName,
            bookingUrl: rtrim(config('app.url'), '/')."/tutor/bookings/{$this->bookingId}",
        ))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'booking.paid',
            'title' => 'Booking confirmed',
            'body' => "{$this->studentName} paid for their {$this->subjectName} booking.",
            'url' => "/tutor/bookings/{$this->bookingId}",
        ];
    }
}
