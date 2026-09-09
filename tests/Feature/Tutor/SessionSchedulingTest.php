<?php

namespace Tests\Feature\Tutor;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Booking\BookingConfirmationService;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Curriculum $curriculum;

    private Grade $grade;

    private ServiceCategory $category;

    private SessionFormat $onlineFormat;

    private string $futureDate;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->onlineFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $this->futureDate = Carbon::now()->addDays(10)->format('Y-m-d');
    }

    /**
     * @return array{0: User, 1: TutorProfile, 2: Service}
     */
    private function createTutorWithService(array $overrides = []): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $service = $tutorProfile->services()->create(array_merge([
            'subject_id' => $this->subject->id,
            'grade_id' => $this->grade->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->onlineFormat->id,
            'title' => 'Grade 10 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 2,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ], $overrides));
        $service->curricula()->attach($this->curriculum->id);

        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $this->futureDate]);
        $availabilityDate->slots()->create(['start_time' => '07:00', 'end_time' => '09:00']);
        $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '11:00']);

        return [$tutorUser, $tutorProfile, $service];
    }

    private function confirmedBookingFor(TutorProfile $tutor, Service $service, ?User $student = null): Booking
    {
        $student ??= User::factory()->create();
        $slot = $tutor->availabilityDates()->first()->slots()->first();

        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '07:00',
            'end_time' => '08:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'accepted',
        ]);

        $order = app(OrderService::class)->createForBooking($booking);
        app(BookingConfirmationService::class)->confirm($order);

        return $booking->fresh();
    }

    private function publishedLessonFor(TutorProfile $tutor): Lesson
    {
        $course = $tutor->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'Intro to algebra.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'published',
        ]);
        $chapter = $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'published']);

        return $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'published']);
    }

    public function test_tutor_can_schedule_the_first_session_for_a_confirmed_booking(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService();
        $booking = $this->confirmedBookingFor($tutorProfile, $service);
        $lesson = $this->publishedLessonFor($tutorProfile);

        Sanctum::actingAs($tutorUser);

        $response = $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'lesson_id' => $lesson->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'tutor_notes' => 'Focus on fractions.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('session.status', 'scheduled');
        $response->assertJsonPath('session.tutor_notes', 'Focus on fractions.');
        $response->assertJsonPath('session.lessons.0', 'What is Algebra?');

        $this->assertDatabaseCount('teaching_sessions', 1);
        $this->assertTrue($booking->teachingSessions()->exists());
    }

    public function test_booking_progress_reflects_purchased_and_remaining_counts(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService(['sessions_included' => 2]);
        $booking = $this->confirmedBookingFor($tutorProfile, $service);

        Sanctum::actingAs($tutorUser);

        $before = $this->getJson("/api/tutor/bookings/{$booking->id}");
        $before->assertOk();
        $before->assertJsonPath('progress.purchased_sessions', 2);
        $before->assertJsonPath('progress.scheduled_sessions', 0);
        $before->assertJsonPath('progress.remaining_sessions', 2);

        $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertCreated();

        $after = $this->getJson("/api/tutor/bookings/{$booking->id}");
        $after->assertJsonPath('progress.scheduled_sessions', 1);
        $after->assertJsonPath('progress.remaining_sessions', 1);
        $after->assertJsonPath('progress.upcoming_sessions', 1);
    }

    public function test_scheduling_beyond_the_purchased_session_count_is_rejected(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService(['sessions_included' => 1]);
        $booking = $this->confirmedBookingFor($tutorProfile, $service);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '07:00', 'end_time' => '08:00',
        ])->assertCreated();

        $second = $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $second->assertUnprocessable();
        $this->assertDatabaseCount('teaching_sessions', 1);
    }

    public function test_scheduling_on_a_non_confirmed_booking_is_rejected(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService();
        $student = User::factory()->create();
        $slot = $tutorProfile->availabilityDates()->first()->slots()->first();

        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '07:00',
            'end_time' => '08:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($tutorUser);

        $response = $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('teaching_sessions', 0);
    }

    public function test_overlapping_session_for_a_different_service_is_rejected(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService();
        [, , $otherService] = $this->createTutorWithService();
        // Reuse the same tutor for the second service to force a genuine
        // overlap check across two different services.
        $otherService->update(['tutor_profile_id' => $tutorProfile->id]);

        $bookingA = $this->confirmedBookingFor($tutorProfile, $service);
        $bookingB = $this->confirmedBookingFor($tutorProfile, $otherService);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/bookings/{$bookingA->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();

        // Overlaps the first session's 09:00-10:00 window but for a
        // different service — a genuine double-booking, must be rejected.
        $response = $this->postJson("/api/tutor/bookings/{$bookingB->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:30', 'end_time' => '10:30',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('teaching_sessions', 1);
    }

    public function test_two_bookings_for_the_same_group_class_time_attach_to_the_same_session(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService(['max_students_per_session' => 2]);
        $bookingA = $this->confirmedBookingFor($tutorProfile, $service);
        $bookingB = $this->confirmedBookingFor($tutorProfile, $service);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/bookings/{$bookingA->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();

        $response = $this->postJson("/api/tutor/bookings/{$bookingB->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('teaching_sessions', 1);
        $response->assertJsonPath('session.participants_count', 2);
    }

    public function test_group_session_rejects_once_capacity_is_reached(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService(['max_students_per_session' => 1]);
        $bookingA = $this->confirmedBookingFor($tutorProfile, $service);
        $bookingB = $this->confirmedBookingFor($tutorProfile, $service);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/bookings/{$bookingA->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();

        $response = $this->postJson("/api/tutor/bookings/{$bookingB->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $response->assertUnprocessable();
    }

    public function test_unpublished_lesson_is_rejected(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService();
        $booking = $this->confirmedBookingFor($tutorProfile, $service);

        $course = $tutorProfile->courses()->create([
            'curriculum_id' => $this->curriculum->id, 'grade_id' => $this->grade->id, 'subject_id' => $this->subject->id,
            'title' => 'Algebra', 'description' => 'x', 'estimated_duration_minutes' => 60,
            'difficulty' => 'beginner', 'language' => 'English', 'status' => 'draft',
        ]);
        $chapter = $course->chapters()->create(['title' => 'Intro', 'position' => 0, 'status' => 'draft']);
        $draftLesson = $chapter->lessons()->create(['title' => 'Draft Lesson', 'position' => 0, 'status' => 'draft']);

        Sanctum::actingAs($tutorUser);

        $response = $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'lesson_id' => $draftLesson->id,
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('lesson_id');
    }

    public function test_tutor_cannot_schedule_a_session_for_another_tutors_booking(): void
    {
        [, $tutorProfile, $service] = $this->createTutorWithService();
        $booking = $this->confirmedBookingFor($tutorProfile, $service);

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $intruder->id]);
        Sanctum::actingAs($intruder);

        $response = $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $response->assertForbidden();
    }

    public function test_completing_the_only_session_marks_the_booking_completed(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService(['sessions_included' => 1]);
        $booking = $this->confirmedBookingFor($tutorProfile, $service);

        Sanctum::actingAs($tutorUser);

        $created = $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();
        $sessionId = $created->json('session.id');

        $this->assertSame('confirmed', $booking->fresh()->status->value);

        $this->patchJson("/api/tutor/sessions/{$sessionId}/complete")->assertOk();

        $this->assertSame('completed', $booking->fresh()->status->value);
    }

    public function test_completing_one_of_two_purchased_sessions_leaves_the_booking_confirmed(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService(['sessions_included' => 2]);
        $booking = $this->confirmedBookingFor($tutorProfile, $service);

        Sanctum::actingAs($tutorUser);

        $first = $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '07:00', 'end_time' => '08:00',
        ])->assertCreated();
        $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();

        $this->patchJson("/api/tutor/sessions/{$first->json('session.id')}/complete")->assertOk();

        // Only one of the two purchased sessions is done — booking stays Confirmed.
        $this->assertSame('confirmed', $booking->fresh()->status->value);
    }

    public function test_completing_a_shared_group_session_completes_every_attached_booking(): void
    {
        [$tutorUser, $tutorProfile, $service] = $this->createTutorWithService([
            'sessions_included' => 1, 'max_students_per_session' => 2,
        ]);
        $bookingA = $this->confirmedBookingFor($tutorProfile, $service);
        $bookingB = $this->confirmedBookingFor($tutorProfile, $service);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/bookings/{$bookingA->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();
        $created = $this->postJson("/api/tutor/bookings/{$bookingB->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();

        $this->patchJson("/api/tutor/sessions/{$created->json('session.id')}/complete")->assertOk();

        $this->assertSame('completed', $bookingA->fresh()->status->value);
        $this->assertSame('completed', $bookingB->fresh()->status->value);
    }
}
