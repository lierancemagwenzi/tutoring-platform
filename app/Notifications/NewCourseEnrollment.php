<?php

namespace App\Notifications;

use App\Mail\NewCourseEnrollmentMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to a tutor when a student enrolls in one of their self-paced
 * courses — see EnrollmentService::activate(). The tutor-assisted
 * equivalent is NewBookingRequest.
 */
class NewCourseEnrollment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $studentName,
        private readonly string $courseTitle,
        private readonly string $courseUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): NewCourseEnrollmentMail
    {
        return (new NewCourseEnrollmentMail(
            tutorFirstName: $notifiable->first_name,
            studentName: $this->studentName,
            courseTitle: $this->courseTitle,
            courseUrl: $this->courseUrl,
        ))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'enrollment.created',
            'title' => 'New enrollment',
            'body' => "{$this->studentName} enrolled in \"{$this->courseTitle}\".",
            'url' => '/tutor/self-paced-courses',
        ];
    }
}
