<?php

namespace App\Notifications;

use App\Mail\TutorApprovedMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Replaces the generic UserNotification for this one event so approval
 * also reaches the tutor by email, not just the in-app notification
 * center — see TutorApprovalService::approve().
 */
class TutorApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $dashboardUrl) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): TutorApprovedMail
    {
        return (new TutorApprovedMail(
            firstName: $notifiable->first_name,
            dashboardUrl: $this->dashboardUrl,
        ))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tutor.approved',
            'title' => 'Application approved',
            'body' => 'Your tutor application has been approved. You can now start accepting bookings.',
            'url' => '/tutor',
        ];
    }
}
