<?php

namespace Tests\Feature\Student;

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

    private function bookingWithStatus(string $status, ?User $student = null): Booking
    {
        $student ??= User::factory()->create();
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
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

    public function test_student_can_view_messages_on_their_own_booking_regardless_of_status(): void
    {
        $student = User::factory()->create();
        $booking = $this->bookingWithStatus('completed', $student);
        $booking->messages()->create(['sender_id' => $booking->tutorProfile->user_id, 'body' => 'Hi there!']);
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/bookings/{$booking->id}/messages");

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonPath('messages.0.body', 'Hi there!');
    }

    public function test_student_can_send_a_message_on_a_confirmed_booking(): void
    {
        $student = User::factory()->create();
        $booking = $this->bookingWithStatus('confirmed', $student);
        Sanctum::actingAs($student);

        $response = $this->postJson("/api/bookings/{$booking->id}/messages", ['body' => 'What should I prepare?']);

        $response->assertCreated();
        $response->assertJsonPath('message.body', 'What should I prepare?');
        $response->assertJsonPath('message.sender.id', $student->id);
        $this->assertDatabaseHas('booking_messages', ['booking_id' => $booking->id, 'sender_id' => $student->id]);
    }

    public function test_sending_a_message_emails_the_tutor(): void
    {
        Mail::fake();
        $student = User::factory()->create();
        $booking = $this->bookingWithStatus('confirmed', $student);
        Sanctum::actingAs($student);

        $this->postJson("/api/bookings/{$booking->id}/messages", ['body' => 'What should I prepare?'])->assertCreated();

        Mail::assertSent(NewBookingMessageMail::class, function ($mail) use ($booking) {
            return $mail->hasTo($booking->tutorProfile->user->email) && $mail->messageBody === 'What should I prepare?';
        });
    }

    public function test_sending_is_blocked_on_a_non_confirmed_booking(): void
    {
        $student = User::factory()->create();
        $booking = $this->bookingWithStatus('pending', $student);
        Sanctum::actingAs($student);

        $response = $this->postJson("/api/bookings/{$booking->id}/messages", ['body' => 'Hello?']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('booking_messages', ['booking_id' => $booking->id]);
    }

    public function test_student_cannot_view_or_send_on_another_students_booking(): void
    {
        $booking = $this->bookingWithStatus('confirmed');
        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/bookings/{$booking->id}/messages")->assertForbidden();
        $this->postJson("/api/bookings/{$booking->id}/messages", ['body' => 'Hi'])->assertForbidden();
    }

    public function test_a_message_sent_by_the_tutor_is_visible_to_the_student(): void
    {
        $student = User::factory()->create();
        $booking = $this->bookingWithStatus('confirmed', $student);
        $tutorUser = $booking->tutorProfile->user;
        Sanctum::actingAs($tutorUser);
        $this->postJson("/api/tutor/bookings/{$booking->id}/messages", ['body' => 'See you Friday.'])->assertCreated();

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/bookings/{$booking->id}/messages");

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonPath('messages.0.body', 'See you Friday.');
        $response->assertJsonPath('messages.0.sender.id', $tutorUser->id);
    }
}
