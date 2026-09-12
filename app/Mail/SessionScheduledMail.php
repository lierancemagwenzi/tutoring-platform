<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionScheduledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $studentFirstName,
        public readonly string $tutorName,
        public readonly string $subjectName,
        public readonly string $date,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly string $bookingUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->subjectName} session is scheduled",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking.session-scheduled',
        );
    }
}
