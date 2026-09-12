<?php

namespace Tests\Feature\Commerce;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\User;
use App\Notifications\BookingPaid;
use App\Notifications\SessionScheduled;
use App\Services\Booking\BookingConfirmationService;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function tutorWithService(array $serviceOverrides = [], ?TutorProfile $tutorProfile = null): array
    {
        $subject = Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true]);
        $category = ServiceCategory::firstOrCreate(['name' => 'Private Lesson'], ['is_active' => true]);
        $format = SessionFormat::firstOrCreate(['name' => 'Online'], ['is_active' => true]);

        if (! $tutorProfile) {
            $tutorUser = User::factory()->tutor()->create();
            $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        }

        $service = $tutorProfile->services()->create(array_merge([
            'subject_id' => $subject->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 12 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 2,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ], $serviceOverrides));

        return [$tutorProfile, $service];
    }

    public function test_confirming_a_booking_auto_schedules_its_first_session(): void
    {
        Queue::fake();
        Notification::fake();

        [$tutorProfile, $service] = $this->tutorWithService();
        $futureDate = Carbon::now()->addDays(10)->format('Y-m-d');

        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $futureDate]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        $student = User::factory()->create();
        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'accepted',
        ]);

        $order = app(OrderService::class)->createForBooking($booking);
        app(BookingConfirmationService::class)->confirm($order);

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertDatabaseCount('teaching_sessions', 1);

        $session = $booking->teachingSessions()->first();
        $this->assertNotNull($session);
        $this->assertSame($futureDate, $session->date->format('Y-m-d'));
        $this->assertSame('09:00', $session->start_time);

        Notification::assertSentTo($student, SessionScheduled::class);
        Notification::assertSentTo($tutorProfile->user, BookingPaid::class);
    }

    public function test_confirming_a_booking_leaves_it_confirmed_even_if_the_original_slot_no_longer_fits(): void
    {
        Queue::fake();
        Notification::fake();

        [$tutorProfile, $service] = $this->tutorWithService();
        $futureDate = Carbon::now()->addDays(10)->format('Y-m-d');

        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $futureDate]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        $student = User::factory()->create();
        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'accepted',
        ]);

        // The tutor already has another session (for a different service)
        // overlapping the exact window the student originally requested —
        // e.g. booked elsewhere between the request and payment clearing.
        [, $otherService] = $this->tutorWithService(['title' => 'Grade 12 Science'], $tutorProfile);
        TeachingSession::create([
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $otherService->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $order = app(OrderService::class)->createForBooking($booking);
        app(BookingConfirmationService::class)->confirm($order);

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status->value);
        // Still just the pre-existing session — nothing was attached to it
        // for this booking, and no second session was created.
        $this->assertDatabaseCount('teaching_sessions', 1);
        $this->assertFalse($booking->teachingSessions()->exists());

        Notification::assertSentTo($tutorProfile->user, BookingPaid::class);
    }
}
