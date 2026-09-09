<?php

namespace Tests\Feature\Tutor;

use App\Enums\MeetingStatus;
use App\Jobs\CancelSessionMeetingJob;
use App\Jobs\CreateSessionMeetingJob;
use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Booking\BookingConfirmationService;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $futureDate;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->futureDate = Carbon::now()->addDays(10)->format('Y-m-d');
    }

    /**
     * @return array{0: User, 1: TutorProfile, 2: Booking}
     */
    private function scheduledSessionFor(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);

        $service = $tutor->services()->create([
            'subject_id' => $subject->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths',
            'description' => 'x',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 2,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ]);

        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => $this->futureDate]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '11:00']);

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

        Sanctum::actingAs($tutorUser);
        $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();

        return [$tutorUser, $tutor, $booking->fresh()];
    }

    /**
     * @return array{0: User, 1: TutorProfile, 2: Booking, 3: TeachingSession, 4: TeachingSession}
     */
    private function twoScheduledSessionsFor(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);

        $service = $tutor->services()->create([
            'subject_id' => $subject->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths',
            'description' => 'x',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 2,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ]);

        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => $this->futureDate]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '11:00']);

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

        Sanctum::actingAs($tutorUser);
        $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();
        $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '10:00', 'end_time' => '11:00',
        ])->assertCreated();

        $booking = $booking->fresh();
        $sessions = $booking->teachingSessions()->orderBy('start_time')->get();

        return [$tutorUser, $tutor, $booking, $sessions[0], $sessions[1]];
    }

    public function test_tutor_can_mark_a_session_completed(): void
    {
        [$tutorUser, , $booking] = $this->scheduledSessionFor();
        $session = $booking->teachingSessions()->first();

        Sanctum::actingAs($tutorUser);

        $response = $this->patchJson("/api/tutor/sessions/{$session->id}/complete", ['tutor_notes' => 'Went well.']);

        $response->assertOk();
        $response->assertJsonPath('session.status', 'completed');
        $response->assertJsonPath('session.tutor_notes', 'Went well.');
        $this->assertNotNull($session->fresh()->completed_at);
    }

    public function test_completing_an_already_completed_session_is_rejected(): void
    {
        [$tutorUser, , $booking] = $this->scheduledSessionFor();
        $session = $booking->teachingSessions()->first();

        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/sessions/{$session->id}/complete")->assertOk();

        $response = $this->patchJson("/api/tutor/sessions/{$session->id}/complete");
        $response->assertUnprocessable();
    }

    public function test_tutor_cannot_complete_a_session_before_an_earlier_session_in_the_same_booking(): void
    {
        [$tutorUser, , , , $session2] = $this->twoScheduledSessionsFor();

        Sanctum::actingAs($tutorUser);
        $response = $this->patchJson("/api/tutor/sessions/{$session2->id}/complete");

        $response->assertUnprocessable();
        $this->assertNull($session2->fresh()->completed_at);
    }

    public function test_tutor_can_complete_sessions_in_chronological_order(): void
    {
        [$tutorUser, , , $session1, $session2] = $this->twoScheduledSessionsFor();

        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/sessions/{$session1->id}/complete")->assertOk();
        $this->patchJson("/api/tutor/sessions/{$session2->id}/complete")->assertOk();

        $this->assertNotNull($session2->fresh()->completed_at);
    }

    public function test_a_cancelled_earlier_session_does_not_block_completing_a_later_session(): void
    {
        [$tutorUser, , , $session1, $session2] = $this->twoScheduledSessionsFor();

        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/sessions/{$session1->id}/cancel")->assertOk();

        $response = $this->patchJson("/api/tutor/sessions/{$session2->id}/complete");
        $response->assertOk();
    }

    public function test_tutor_can_cancel_a_scheduled_session_and_reschedule_another(): void
    {
        [$tutorUser, , $booking] = $this->scheduledSessionFor();
        $session = $booking->teachingSessions()->first();

        Sanctum::actingAs($tutorUser);

        $response = $this->patchJson("/api/tutor/sessions/{$session->id}/cancel");
        $response->assertOk();
        $response->assertJsonPath('session.status', 'cancelled');

        // Cancelling frees the purchased slot back up.
        $progress = $this->getJson("/api/tutor/bookings/{$booking->id}");
        $progress->assertJsonPath('progress.remaining_sessions', 2);

        $reschedule = $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);
        $reschedule->assertCreated();
    }

    public function test_cancelling_a_session_marks_its_scheduled_meeting_cancelled_and_queues_remote_deletion(): void
    {
        [$tutorUser, , $booking] = $this->scheduledSessionFor();
        $session = $booking->teachingSessions()->first();
        $meeting = $session->sessionMeeting;
        $meeting->update([
            'provider' => 'google',
            'calendar_event_id' => 'evt-1',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'status' => MeetingStatus::Scheduled,
        ]);

        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/sessions/{$session->id}/cancel")->assertOk();

        $this->assertSame(MeetingStatus::Cancelled, $meeting->fresh()->status);
        Queue::assertPushed(CancelSessionMeetingJob::class, fn ($job) => $job->sessionMeetingId === $meeting->id);
    }

    public function test_cancelling_a_session_without_a_remote_meeting_does_not_queue_deletion(): void
    {
        [$tutorUser, , $booking] = $this->scheduledSessionFor();
        $session = $booking->teachingSessions()->first();
        $meeting = $session->sessionMeeting;
        // Still Pending — CreateSessionMeetingJob never ran because Queue::fake().
        $this->assertNull($meeting->calendar_event_id);

        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/sessions/{$session->id}/cancel")->assertOk();

        $this->assertSame(MeetingStatus::Cancelled, $meeting->fresh()->status);
        Queue::assertNotPushed(CancelSessionMeetingJob::class);
    }

    public function test_rescheduling_a_cancelled_session_resets_its_meeting_for_recreation(): void
    {
        [$tutorUser, , $booking] = $this->scheduledSessionFor();
        $session = $booking->teachingSessions()->first();
        $meeting = $session->sessionMeeting;
        $meeting->update([
            'provider' => 'google',
            'calendar_event_id' => 'evt-1',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'status' => MeetingStatus::Scheduled,
        ]);

        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/sessions/{$session->id}/cancel")->assertOk();
        $this->assertSame(MeetingStatus::Cancelled, $meeting->fresh()->status);

        $this->postJson("/api/tutor/bookings/{$booking->id}/sessions", [
            'date' => $this->futureDate, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertCreated();

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Pending, $meeting->status);
        $this->assertNull($meeting->calendar_event_id);
        $this->assertNull($meeting->meeting_url);
        Queue::assertPushed(CreateSessionMeetingJob::class, fn ($job) => $job->sessionMeetingId === $meeting->id);
    }

    public function test_a_completed_session_cannot_be_cancelled(): void
    {
        [$tutorUser, , $booking] = $this->scheduledSessionFor();
        $session = $booking->teachingSessions()->first();

        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/sessions/{$session->id}/complete")->assertOk();

        $response = $this->patchJson("/api/tutor/sessions/{$session->id}/cancel");
        $response->assertUnprocessable();
    }

    public function test_tutor_cannot_complete_another_tutors_session(): void
    {
        [, , $booking] = $this->scheduledSessionFor();
        $session = $booking->teachingSessions()->first();

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $intruder->id]);
        Sanctum::actingAs($intruder);

        $this->patchJson("/api/tutor/sessions/{$session->id}/complete")->assertForbidden();
        $this->patchJson("/api/tutor/sessions/{$session->id}/cancel")->assertForbidden();
    }
}
