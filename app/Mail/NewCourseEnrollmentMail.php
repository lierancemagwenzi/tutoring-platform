<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCourseEnrollmentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $tutorFirstName,
        public readonly string $studentName,
        public readonly string $courseTitle,
        public readonly string $courseUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New enrollment in \"{$this->courseTitle}\"",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tutor.new-enrollment',
        );
    }
}
