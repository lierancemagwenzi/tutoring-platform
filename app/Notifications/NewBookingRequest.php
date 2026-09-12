<?php

namespace App\Notifications;

use App\Mail\NewBookingRequestMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to a tutor when a student requests a tutor-assisted booking — see
 * BookingController::store(). The self-paced equivalent is
 * NewCourseEnrollment.
 */
class NewBookingRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $studentName,
        private readonly string $subjectName,
        private readonly string $bookingUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): NewBookingRequestMail
    {
        return (new NewBookingRequestMail(
            tutorFirstName: $notifiable->first_name,
            studentName: $this->studentName,
            subjectName: $this->subjectName,
            bookingUrl: $this->bookingUrl,
        ))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'booking.created',
            'title' => 'New booking request',
            'body' => "{$this->studentName} requested a {$this->subjectName} session.",
            'url' => '/tutor/booking-requests',
        ];
    }
}
