<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously (not queued) — see BookingChatService::notifyRecipient()
 * — so it doesn't depend on a queue worker running, matching AdminTestEmail's
 * precedent.
 */
class NewBookingMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientFirstName,
        public readonly string $senderName,
        public readonly string $messageBody,
        public readonly string $bookingUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New message from {$this->senderName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking.new-message',
        );
    }
}
