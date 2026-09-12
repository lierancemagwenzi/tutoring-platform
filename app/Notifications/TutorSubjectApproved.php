<?php

namespace App\Notifications;

use App\Mail\TutorSubjectApprovedMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Replaces the generic UserNotification for this one event so subject
 * approval also reaches the tutor by email, not just the in-app
 * notification center — see TutorSubjectApprovalService::approve().
 */
class TutorSubjectApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $subjectName,
        private readonly string $dashboardUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): TutorSubjectApprovedMail
    {
        return (new TutorSubjectApprovedMail(
            firstName: $notifiable->first_name,
            subjectName: $this->subjectName,
            dashboardUrl: $this->dashboardUrl,
        ))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tutor_subject.approved',
            'title' => 'Subject approved',
            'body' => "Your request to teach \"{$this->subjectName}\" was approved.",
            'url' => '/tutor',
        ];
    }
}
