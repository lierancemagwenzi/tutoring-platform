<?php

namespace App\Notifications;

use App\Mail\SessionScheduledMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to a student when a tutor schedules a session against their
 * confirmed booking — see SessionSchedulingService::scheduleSession().
 */
class SessionScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tutorName,
        private readonly string $subjectName,
        private readonly string $date,
        private readonly string $startTime,
        private readonly string $endTime,
        private readonly int $bookingId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): SessionScheduledMail
    {
        return (new SessionScheduledMail(
            studentFirstName: $notifiable->first_name,
            tutorName: $this->tutorName,
            subjectName: $this->subjectName,
            date: $this->date,
            startTime: $this->startTime,
            endTime: $this->endTime,
            bookingUrl: rtrim(config('app.url'), '/')."/student/bookings/{$this->bookingId}",
        ))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session.scheduled',
            'title' => 'Session scheduled',
            'body' => "{$this->tutorName} scheduled your {$this->subjectName} session for {$this->date} at {$this->startTime}.",
            'url' => "/student/bookings/{$this->bookingId}",
        ];
    }
}
