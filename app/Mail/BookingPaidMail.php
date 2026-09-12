<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $tutorFirstName,
        public readonly string $studentName,
        public readonly string $subjectName,
        public readonly string $bookingUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->studentName} paid for their booking",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking.paid',
        );
    }
}
