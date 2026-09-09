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
use App\Services\Booking\BookingConfirmationService;
use App\Services\Booking\SessionSchedulingService;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeachingSessionTest extends TestCase
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
            'max_students_per_session' => 2,
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

    private function createPaidBooking(TutorProfile $tutor, Service $service, AvailabilitySlot $slot): Booking
    {
        $student = User::factory()->create();

        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'accepted',
        ]);

        $order = app(OrderService::class)->createForBooking($booking);
        app(BookingConfirmationService::class)->confirm($order);
        $booking = $booking->fresh();

        // Payment confirmation no longer auto-creates a session — the
        // tutor now schedules it manually. A second booking for the exact
        // same tutor/service/time attaches to the same TeachingSession
        // (SessionSchedulingService's exact-match/group-class behavior).
        app(SessionSchedulingService::class)->scheduleSession($booking, [
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        return $booking->fresh();
    }

    public function test_tutor_can_list_own_sessions(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $this->createPaidBooking($tutorProfile, $service, $slot);

        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/tutor/sessions');

        $response->assertOk()->assertJsonCount(1, 'sessions');
        $response->assertJsonPath('sessions.0.capacity', 2);
        $response->assertJsonPath('sessions.0.participants_count', 1);
    }

    public function test_session_detail_includes_meeting_and_attached_bookings(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $booking = $this->createPaidBooking($tutorProfile, $service, $slot);

        Sanctum::actingAs($tutorUser);

        $response = $this->getJson("/api/tutor/sessions/{$booking->teachingSessions()->first()->id}");

        $response->assertOk();
        // Meeting creation is deferred to a queued job (dispatched
        // afterCommit), which does run here (sync queue driver, no
        // Queue::fake()) — but the tutor has selected no meeting provider,
        // so it permanently (and correctly) fails rather than scheduling.
        $response->assertJsonPath('session.meeting.provider', 'google');
        $response->assertJsonPath('session.meeting.status', 'failed');
        $response->assertJsonCount(1, 'session.bookings');
        $response->assertJsonPath('session.bookings.0.status', 'confirmed');
    }

    public function test_second_booking_in_same_slot_joins_the_same_session_and_updates_participant_count(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService(['max_students_per_session' => 2]);

        $bookingA = $this->createPaidBooking($tutorProfile, $service, $slot);
        $bookingB = $this->createPaidBooking($tutorProfile, $service, $slot);

        $this->assertEquals($bookingA->teachingSessions()->first()->id, $bookingB->teachingSessions()->first()->id);

        Sanctum::actingAs($tutorUser);

        $response = $this->getJson("/api/tutor/sessions/{$bookingA->teachingSessions()->first()->id}");

        $response->assertOk();
        $response->assertJsonPath('session.participants_count', 2);
        $response->assertJsonCount(2, 'session.bookings');
    }

    public function test_tutor_cannot_view_another_tutors_session(): void
    {
        [, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $booking = $this->createPaidBooking($tutorProfile, $service, $slot);

        $otherTutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $otherTutorUser->id]);
        Sanctum::actingAs($otherTutorUser);

        $this->getJson("/api/tutor/sessions/{$booking->teachingSessions()->first()->id}")->assertForbidden();
    }
}
