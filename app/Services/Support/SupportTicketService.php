<?php

namespace App\Services\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use App\Notifications\UserNotification;
use App\Services\Admin\AdminActivityLogger;

/**
 * General-purpose support tickets — not scoped to any booking or
 * transaction, open to both tutors and students. Comments are two-way.
 * Deliberately separate from PaymentTicket, which is scoped to a
 * FinancialTransaction and tutor-only.
 */
class SupportTicketService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function raise(User $user, string $subject, string $message): SupportTicket
    {
        return SupportTicket::create([
            'user_id' => $user->id,
            'subject' => $subject,
            'message' => $message,
            'status' => SupportTicketStatus::Open,
        ]);
    }

    public function addComment(SupportTicket $ticket, User $author, string $body): SupportTicketComment
    {
        return $ticket->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }

    public function updateStatus(SupportTicket $ticket, SupportTicketStatus $status, User $actor): SupportTicket
    {
        $ticket->update(['status' => $status]);

        $this->logger->log(
            $actor,
            'support_ticket.status_updated',
            $ticket,
            "Moved support ticket #{$ticket->id} to {$status->value}.",
            ['status' => $status->value],
        );

        $ticket->loadMissing('user');
        $ticket->user->notify(new UserNotification(
            type: 'support_ticket.status_updated',
            title: 'Support ticket updated',
            body: "Your support ticket #{$ticket->id} is now {$status->value}.",
            url: "/{$ticket->user->role->value}/help-tickets/{$ticket->id}",
        ));

        return $ticket;
    }
}
