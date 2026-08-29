<?php

namespace Tests\Feature\Tutor;

use App\Mail\NewBookingMessageMail;
use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingChatTest extends TestCase
{
    use RefreshDatabase;

    private function bookingWithStatus(string $status, ?TutorProfile $tutor = null): Booking
    {
        $student = User::factory()->create();
        if (! $tutor) {
            $tutorUser = User::factory()->tutor()->create();
            $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        }
        $subject = Subject::create(['name' => 'Mathematics']);
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);
        $service = $tutor->services()->create([
            'subject_id' => $subject->id, 'service_category_id' => $category->id, 'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths', 'description' => 'Tutoring.', 'price' => 200, 'currency' => 'ZAR',
            'session_duration_minutes' => 60, 'sessions_included' => 1, 'validity_period_days' => 30,
            'max_students_per_session' => 1, 'visibility' => 'published',
        ]);
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => now()->addDays(5)->toDateString()]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '10:00']);

        return Booking::create([
            'student_id' => $student->id, 'tutor_profile_id' => $tutor->id, 'service_id' => $service->id,
            'availability_slot_id' => $slot->id, 'date' => $availabilityDate->date, 'start_time' => '09:00', 'end_time' => '10:00',
            'price' => 200, 'currency' => 'ZAR', 'status' => $status,
        ]);
    }

    public function test_tutor_can_view_messages_on_their_own_booking_regardless_of_status(): void
    {
        $booking = $this->bookingWithStatus('cancelled');
        $booking->messages()->create(['sender_id' => $booking->student_id, 'body' => 'Can we reschedule?']);
        Sanctum::actingAs($booking->tutorProfile->user);

        $response = $this->getJson("/api/tutor/bookings/{$booking->id}/messages");

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonPath('messages.0.body', 'Can we reschedule?');
    }

    public function test_tutor_can_send_a_message_on_a_confirmed_booking(): void
    {
        $booking = $this->bookingWithStatus('confirmed');
        Sanctum::actingAs($booking->tutorProfile->user);

        $response = $this->postJson("/api/tutor/bookings/{$booking->id}/messages", ['body' => "Looking forward to it."]);

        $response->assertCreated();
        $response->assertJsonPath('message.body', 'Looking forward to it.');
        $response->assertJsonPath('message.sender.id', $booking->tutorProfile->user_id);
    }

    public function test_sending_a_message_emails_the_student(): void
    {
        Mail::fake();
        $booking = $this->bookingWithStatus('confirmed');
        Sanctum::actingAs($booking->tutorProfile->user);

        $this->postJson("/api/tutor/bookings/{$booking->id}/messages", ['body' => 'Looking forward to it.'])->assertCreated();

        Mail::assertSent(NewBookingMessageMail::class, function ($mail) use ($booking) {
            return $mail->hasTo($booking->student->email) && $mail->messageBody === 'Looking forward to it.';
        });
    }

    public function test_sending_is_blocked_on_a_non_confirmed_booking(): void
    {
        $booking = $this->bookingWithStatus('awaiting_payment');
        Sanctum::actingAs($booking->tutorProfile->user);

        $response = $this->postJson("/api/tutor/bookings/{$booking->id}/messages", ['body' => 'Hello?']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('booking_messages', ['booking_id' => $booking->id]);
    }

    public function test_tutor_cannot_view_or_send_on_another_tutors_booking(): void
    {
        $booking = $this->bookingWithStatus('confirmed');
        $otherTutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $otherTutorUser->id, 'display_name' => 'Other Tutor']);
        Sanctum::actingAs($otherTutorUser);

        $this->getJson("/api/tutor/bookings/{$booking->id}/messages")->assertForbidden();
        $this->postJson("/api/tutor/bookings/{$booking->id}/messages", ['body' => 'Hi'])->assertForbidden();
    }
}
