<?php

namespace App\Services\Booking;

use App\Mail\NewBookingMessageMail;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Mail;

class BookingChatService
{
    public function send(Booking $booking, User $sender, string $body): BookingMessage
    {
        $message = $booking->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $this->notifyRecipient($booking, $sender, $message);

        return $message;
    }

    /**
     * Email whichever party didn't send the message — the tutor if the
     * student sent it, or vice versa.
     */
    private function notifyRecipient(Booking $booking, User $sender, BookingMessage $message): void
    {
        $tutorUser = $booking->tutorProfile->user;
        $senderIsTutor = $sender->id === $tutorUser->id;

        $recipient = $senderIsTutor ? $booking->student : $tutorUser;
        $recipientRole = $senderIsTutor ? 'student' : 'tutor';

        Mail::to($recipient->email)->send(new NewBookingMessageMail(
            recipientFirstName: $recipient->first_name,
            senderName: trim("{$sender->first_name} {$sender->last_name}"),
            messageBody: $message->body,
            bookingUrl: rtrim(config('app.url'), '/')."/{$recipientRole}/bookings/{$booking->id}",
        ));

        $recipient->notify(new UserNotification(
            type: 'booking.message',
            title: 'New message',
            body: "{$sender->first_name}: {$message->body}",
            url: "/{$recipientRole}/bookings/{$booking->id}",
        ));
    }
}
