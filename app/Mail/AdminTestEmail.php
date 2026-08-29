<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously (not queued) by Quick Setup's "send test email"
 * action, so the admin gets an immediate success/failure result rather
 * than having to check a queue.
 */
class AdminTestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test email from your platform',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.test-email',
        );
    }
}
