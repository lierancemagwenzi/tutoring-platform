<?php

namespace App\Services\Commerce;

use App\Enums\PaymentTicketStatus;
use App\Models\FinancialTransaction;
use App\Models\PaymentTicket;
use App\Models\PaymentTicketComment;
use App\Models\TutorProfile;
use App\Models\User;
use App\Notifications\UserNotification;
use App\Services\Admin\AdminActivityLogger;

/**
 * A tutor-raised dispute on a single FinancialTransaction (e.g. "payment
 * wasn't received"), worked by the admin through a fixed status workflow.
 * Comments are two-way — both the tutor and the admin can post them.
 */
class PaymentTicketService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function raise(FinancialTransaction $transaction, TutorProfile $tutor, string $message): PaymentTicket
    {
        return PaymentTicket::create([
            'financial_transaction_id' => $transaction->id,
            'tutor_profile_id' => $tutor->id,
            'message' => $message,
            'status' => PaymentTicketStatus::Open,
        ]);
    }

    public function addComment(PaymentTicket $ticket, User $author, string $body): PaymentTicketComment
    {
        return $ticket->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }

    public function updateStatus(PaymentTicket $ticket, PaymentTicketStatus $status, User $actor): PaymentTicket
    {
        $ticket->update(['status' => $status]);

        $this->logger->log(
            $actor,
            'payment_ticket.status_updated',
            $ticket,
            "Moved payment ticket #{$ticket->id} to {$status->value}.",
            ['status' => $status->value],
        );

        $ticket->loadMissing('tutorProfile.user');
        $ticket->tutorProfile->user->notify(new UserNotification(
            type: 'payment_ticket.status_updated',
            title: 'Payment ticket updated',
            body: "Your payment ticket #{$ticket->id} is now {$status->value}.",
            url: "/tutor/payment-tickets/{$ticket->id}",
        ));

        return $ticket;
    }
}
