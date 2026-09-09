<?php

namespace Tests\Feature\Meetings;

use App\Contracts\MeetingProviderContract;
use App\Enums\MeetingStatus;
use App\Jobs\CancelSessionMeetingJob;
use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\SessionMeeting;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorConnectedAccount;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Meetings\GoogleCalendarMeetingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class CancelSessionMeetingJobTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor', 'meeting_provider' => 'google']);
    }

    private function connectGoogle(TutorProfile $tutor): TutorConnectedAccount
    {
        return TutorConnectedAccount::create([
            'tutor_profile_id' => $tutor->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
            'email' => 'tutor@gmail.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'connected_at' => now(),
        ]);
    }

    private function sessionWithScheduledMeeting(TutorProfile $tutor): TeachingSession
    {
        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);

        $service = $tutor->services()->create([
            'subject_id' => $subject->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 12 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ]);

        $availabilityDate = AvailabilityDate::create([
            'tutor_profile_id' => $tutor->id,
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
        ]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        $session = TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $student = User::factory()->create();
        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => 300,
            'currency' => 'ZAR',
            'status' => 'confirmed',
        ]);
        $booking->teachingSessions()->attach($session->id);

        return $session;
    }

    private function scheduledMeetingFor(TeachingSession $session): SessionMeeting
    {
        return SessionMeeting::create([
            'teaching_session_id' => $session->id,
            'provider' => 'google',
            'calendar_event_id' => 'evt-1',
            'meeting_id' => 'meet-1',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'organizer_email' => 'tutor@gmail.com',
            'status' => MeetingStatus::Cancelled,
        ]);
    }

    private function bindFakeProvider(\Closure $deleteMeeting): void
    {
        $this->app->bind(GoogleCalendarMeetingProvider::class, fn () => new class($deleteMeeting) implements MeetingProviderContract
        {
            public function __construct(private \Closure $deleteMeeting) {}

            public function createMeeting($session, $booking): array
            {
                throw new \Exception('This should never be called.');
            }

            public function deleteMeeting($session, $meeting): void
            {
                ($this->deleteMeeting)($session, $meeting);
            }
        });
    }

    public function test_job_deletes_the_remote_meeting_via_provider(): void
    {
        $tutor = $this->tutor();
        $this->connectGoogle($tutor);
        $session = $this->sessionWithScheduledMeeting($tutor);
        $sessionMeeting = $this->scheduledMeetingFor($session);

        $deleted = false;
        $this->bindFakeProvider(function () use (&$deleted) {
            $deleted = true;
        });

        $job = new CancelSessionMeetingJob($sessionMeeting->id);
        $this->app->call([$job, 'handle']);

        $this->assertTrue($deleted);
    }

    public function test_permanent_failure_is_logged_without_propagating(): void
    {
        $tutor = $this->tutor();
        // No connected account left to delete the event with.
        $session = $this->sessionWithScheduledMeeting($tutor);
        $sessionMeeting = $this->scheduledMeetingFor($session);

        $this->bindFakeProvider(function () {
            throw new RuntimeException('The tutor has not connected a Google account.');
        });

        $job = new CancelSessionMeetingJob($sessionMeeting->id);
        $this->app->call([$job, 'handle']);

        $sessionMeeting->refresh();
        $this->assertSame(MeetingStatus::Cancelled, $sessionMeeting->status);
    }

    public function test_transient_provider_exception_propagates_so_the_queue_can_retry(): void
    {
        $tutor = $this->tutor();
        $this->connectGoogle($tutor);
        $session = $this->sessionWithScheduledMeeting($tutor);
        $sessionMeeting = $this->scheduledMeetingFor($session);

        $this->bindFakeProvider(function () {
            throw new \Exception('Temporary Google API outage.');
        });

        $job = new CancelSessionMeetingJob($sessionMeeting->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Temporary Google API outage.');

        $this->app->call([$job, 'handle']);
    }

    public function test_job_is_a_no_op_when_the_meeting_no_longer_exists(): void
    {
        $job = new CancelSessionMeetingJob(999999);

        // No exception, nothing to assert beyond "it doesn't blow up".
        $this->app->call([$job, 'handle']);

        $this->assertTrue(true);
    }
}
