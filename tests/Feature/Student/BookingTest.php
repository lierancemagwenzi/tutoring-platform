<?php

namespace Tests\Feature\Student;

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

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private ServiceCategory $category;

    private SessionFormat $onlineFormat;

    private SessionFormat $inPersonFormat;

    private string $futureDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->onlineFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $this->inPersonFormat = SessionFormat::create(['name' => 'In Person', 'is_active' => true]);
        $this->futureDate = Carbon::now()->addDays(10)->format('Y-m-d');
    }

    /**
     * @return array{0: TutorProfile, 1: Service, 2: AvailabilitySlot}
     */
    private function createTutorWithService(array $serviceOverrides = [], array $slotOverrides = []): array
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

        $slot = $availabilityDate->slots()->create(array_merge([
            'start_time' => '09:00',
            'end_time' => '12:00',
        ], $slotOverrides));

        return [$tutorProfile, $service, $slot];
    }

    private function actingStudent(): User
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        return $student;
    }

    private function createBooking(User $student, TutorProfile $tutor, Service $service, AvailabilitySlot $slot, array $overrides = []): Booking
    {
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
        ], $overrides));
    }

    public function test_availability_endpoint_returns_bookable_times_within_slot_bounds(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $this->actingStudent();

        $month = Carbon::parse($this->futureDate)->format('Y-m');

        $response = $this->getJson("/api/marketplace/tutors/{$tutor->id}/services/{$service->id}/availability?month={$month}");

        $response->assertOk();
        $response->assertJsonCount(3, "availability.{$this->futureDate}");
        $response->assertJsonPath("availability.{$this->futureDate}.0.start_time", '09:00');
        $response->assertJsonPath("availability.{$this->futureDate}.0.availability_slot_id", $slot->id);
        $response->assertJsonPath("availability.{$this->futureDate}.1.start_time", '10:00');
        $response->assertJsonPath("availability.{$this->futureDate}.2.start_time", '11:00');
    }

    public function test_student_can_create_a_booking_request(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $this->actingStudent();

        $response = $this->postJson("/api/marketplace/tutors/{$tutor->id}/services/{$service->id}/bookings", [
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'message' => 'Please focus on Algebra.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('booking.status', 'pending');
        $response->assertJsonPath('booking.message', 'Please focus on Algebra.');

        $this->assertDatabaseHas('bookings', [
            'service_id' => $service->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'status' => 'pending',
        ]);
    }

    public function test_cannot_book_an_unpublished_service(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService(['visibility' => 'draft']);
        $this->actingStudent();

        $response = $this->postJson("/api/marketplace/tutors/{$tutor->id}/services/{$service->id}/bookings", [
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('service');
    }

    public function test_cannot_book_a_time_outside_the_slot(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $this->actingStudent();

        $response = $this->postJson("/api/marketplace/tutors/{$tutor->id}/services/{$service->id}/bookings", [
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '13:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    public function test_duplicate_pending_booking_is_rejected(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $student = $this->actingStudent();

        $this->createBooking($student, $tutor, $service, $slot);

        $response = $this->postJson("/api/marketplace/tutors/{$tutor->id}/services/{$service->id}/bookings", [
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    public function test_student_can_list_and_filter_own_bookings(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $student = $this->actingStudent();

        $this->createBooking($student, $tutor, $service, $slot, ['status' => 'pending']);
        $this->createBooking($student, $tutor, $service, $slot, ['status' => 'rejected', 'start_time' => '10:00', 'end_time' => '11:00']);

        $this->getJson('/api/bookings')->assertOk()->assertJsonCount(2, 'bookings');
        $this->getJson('/api/bookings?status=pending')->assertOk()->assertJsonCount(1, 'bookings');
    }

    public function test_student_cannot_view_another_students_booking(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $owner = User::factory()->create();
        $booking = $this->createBooking($owner, $tutor, $service, $slot);

        $this->actingStudent();

        $this->getJson("/api/bookings/{$booking->id}")->assertForbidden();
    }

    public function test_student_can_cancel_a_pending_booking(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $student = $this->actingStudent();
        $booking = $this->createBooking($student, $tutor, $service, $slot);

        $response = $this->patchJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()->assertJsonPath('booking.status', 'cancelled');
    }

    public function test_student_can_cancel_an_awaiting_payment_booking(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $student = $this->actingStudent();
        $booking = $this->createBooking($student, $tutor, $service, $slot, ['status' => 'awaiting_payment']);

        $response = $this->patchJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()->assertJsonPath('booking.status', 'cancelled');
    }

    public function test_cannot_cancel_a_confirmed_booking(): void
    {
        [$tutor, $service, $slot] = $this->createTutorWithService();
        $student = $this->actingStudent();
        $booking = $this->createBooking($student, $tutor, $service, $slot, [
            'status' => 'confirmed',
        ]);

        $response = $this->patchJson("/api/bookings/{$booking->id}/cancel");

        $response->assertUnprocessable();
    }
}
