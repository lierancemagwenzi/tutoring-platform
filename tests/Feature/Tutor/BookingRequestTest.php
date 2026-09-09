<?php

namespace Tests\Feature\Tutor;

use App\Models\AvailabilityDate;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private ServiceCategory $category;

    private SessionFormat $onlineFormat;

    private string $futureDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->onlineFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $this->futureDate = Carbon::now()->addDays(10)->format('Y-m-d');
    }

    /**
     * @return array{0: User, 1: TutorProfile, 2: Service, 3: AvailabilitySlot}
     */
    private function createTutorWithService(array $serviceOverrides = []): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $service = $tutorProfile->services()->create(array_merge([
            'subject_id' => $this->subject->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->onlineFormat->id,
            'title' => 'Grade 12 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ], $serviceOverrides));

        $availabilityDate = AvailabilityDate::create([
            'tutor_profile_id' => $tutorProfile->id,
            'date' => $this->futureDate,
        ]);

        $slot = $availabilityDate->slots()->create([
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        return [$tutorUser, $tutorProfile, $service, $slot];
    }

    private function createBooking(TutorProfile $tutor, Service $service, AvailabilitySlot $slot, array $overrides = []): Booking
    {
        $student = User::factory()->create();

        return Booking::create(array_merge([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'pending',
            'message' => 'Please focus on Algebra.',
        ], $overrides));
    }

    public function test_tutor_can_list_own_booking_requests_filtered_by_status(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        Sanctum::actingAs($tutorUser);

        $this->createBooking($tutorProfile, $service, $slot, ['status' => 'pending']);
        $this->createBooking($tutorProfile, $service, $slot, ['status' => 'rejected', 'start_time' => '10:00', 'end_time' => '11:00']);

        $this->getJson('/api/tutor/booking-requests')->assertOk()->assertJsonCount(2, 'bookings');
        $this->getJson('/api/tutor/booking-requests?status=pending')->assertOk()->assertJsonCount(1, 'bookings');
    }

    public function test_booking_request_shows_student_message_and_service(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        Sanctum::actingAs($tutorUser);

        $booking = $this->createBooking($tutorProfile, $service, $slot);

        $response = $this->getJson("/api/tutor/booking-requests/{$booking->id}");

        $response->assertOk();
        $response->assertJsonPath('booking.message', 'Please focus on Algebra.');
        $response->assertJsonPath('booking.service.title', 'Grade 12 Maths');
    }

    public function test_tutor_can_accept_a_request_when_the_stored_time_includes_seconds(): void
    {
        // Regression test: MySQL normalizes TIME columns to "H:i:s" on read (e.g. "09:00:00"),
        // while SQLite (used by the test suite) stores the literal string given (e.g. "09:00").
        // The capacity check must normalize before comparing so this passes identically on
        // both drivers rather than only appearing to work under SQLite.
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        Sanctum::actingAs($tutorUser);

        $booking = $this->createBooking($tutorProfile, $service, $slot, [
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $response = $this->patchJson("/api/tutor/booking-requests/{$booking->id}/accept");

        $response->assertOk()->assertJsonPath('booking.status', 'awaiting_payment');
    }

    public function test_tutor_can_accept_a_pending_request(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        Sanctum::actingAs($tutorUser);

        $booking = $this->createBooking($tutorProfile, $service, $slot);

        $response = $this->patchJson("/api/tutor/booking-requests/{$booking->id}/accept");

        $response->assertOk()->assertJsonPath('booking.status', 'awaiting_payment');
        $response->assertJsonPath('booking.order_id', fn ($value) => $value !== null);

        $booking->refresh();
        $this->assertNotNull($booking->order_id);
        $this->assertSame($booking->student_id, $booking->order->student_id);
        $this->assertSame('tutoring_service_booking', $booking->order->items->first()->product_type);
        $this->assertSame((float) $service->price, (float) $booking->order->final_amount);
    }

    public function test_tutor_can_reject_a_pending_request(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        Sanctum::actingAs($tutorUser);

        $booking = $this->createBooking($tutorProfile, $service, $slot);

        $response = $this->patchJson("/api/tutor/booking-requests/{$booking->id}/reject");

        $response->assertOk()->assertJsonPath('booking.status', 'rejected');
    }

    public function test_cannot_accept_a_request_that_is_no_longer_pending(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        Sanctum::actingAs($tutorUser);

        $booking = $this->createBooking($tutorProfile, $service, $slot, ['status' => 'rejected']);

        $response = $this->patchJson("/api/tutor/booking-requests/{$booking->id}/accept");

        $response->assertUnprocessable();
    }

    public function test_accepting_beyond_capacity_is_blocked(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService(['max_students_per_session' => 1]);
        Sanctum::actingAs($tutorUser);

        $alreadyAwaitingPayment = $this->createBooking($tutorProfile, $service, $slot, ['status' => 'awaiting_payment']);
        $pending = $this->createBooking($tutorProfile, $service, $slot, ['status' => 'pending']);

        $response = $this->patchJson("/api/tutor/booking-requests/{$pending->id}/accept");

        $response->assertUnprocessable();
        $this->assertEquals('awaiting_payment', $alreadyAwaitingPayment->fresh()->status->value);
        $this->assertEquals('pending', $pending->fresh()->status->value);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_tutor_cannot_manage_another_tutors_booking_request(): void
    {
        [$ownerUser, $ownerProfile, $service, $slot] = $this->createTutorWithService();
        $booking = $this->createBooking($ownerProfile, $service, $slot);

        $otherTutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $otherTutorUser->id]);
        Sanctum::actingAs($otherTutorUser);

        $this->getJson("/api/tutor/booking-requests/{$booking->id}")->assertForbidden();
        $this->patchJson("/api/tutor/booking-requests/{$booking->id}/accept")->assertForbidden();
        $this->patchJson("/api/tutor/booking-requests/{$booking->id}/reject")->assertForbidden();
    }
}
