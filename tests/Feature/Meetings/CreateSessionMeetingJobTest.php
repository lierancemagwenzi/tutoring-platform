<?php

namespace Tests\Feature\Meetings;

use App\Contracts\MeetingProviderContract;
use App\Enums\MeetingStatus;
use App\Jobs\CreateSessionMeetingJob;
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
use App\Services\Meetings\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class CreateSessionMeetingJobTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
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

    /**
     * @return array{0: TeachingSession, 1: Booking}
     */
    private function sessionWithBooking(TutorProfile $tutor): array
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

        return [$session, $booking];
    }

    private function pendingMeetingFor(TeachingSession $session): SessionMeeting
    {
        return SessionMeeting::create([
            'teaching_session_id' => $session->id,
            'status' => MeetingStatus::Pending,
        ]);
    }

    private function bindFakeProvider(\Closure $createMeeting): void
    {
        $this->app->bind(GoogleCalendarMeetingProvider::class, fn () => new class($createMeeting) implements MeetingProviderContract
        {
            public function __construct(private \Closure $createMeeting) {}

            public function createMeeting($session, $booking): array
            {
                return ($this->createMeeting)($session, $booking);
            }

            public function deleteMeeting($session, $meeting): void {}
        });
    }

    public function test_job_creates_meeting_via_provider_and_marks_it_scheduled(): void
    {
        $tutor = $this->tutor();
        $this->connectGoogle($tutor);
        $tutor->update(['meeting_provider' => 'google']);
        [$session] = $this->sessionWithBooking($tutor);
        $sessionMeeting = $this->pendingMeetingFor($session);

        $this->bindFakeProvider(fn () => [
            'calendar_event_id' => 'evt-1',
            'meeting_id' => 'meet-1',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'organizer_email' => 'tutor@gmail.com',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHour(),
            'metadata' => ['html_link' => 'https://calendar.google.com/event'],
        ]);

        $job = new CreateSessionMeetingJob($sessionMeeting->id);
        $this->app->call([$job, 'handle']);

        $sessionMeeting->refresh();
        $this->assertSame(MeetingStatus::Scheduled, $sessionMeeting->status);
        $this->assertSame('evt-1', $sessionMeeting->calendar_event_id);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $sessionMeeting->meeting_url);
        $this->assertSame('google', $sessionMeeting->provider->value);
    }

    public function test_permanent_failure_marks_meeting_failed_without_propagating(): void
    {
        $tutor = $this->tutor();
        // No meeting_provider selected and no connected account.
        [$session] = $this->sessionWithBooking($tutor);
        $sessionMeeting = $this->pendingMeetingFor($session);

        $job = new CreateSessionMeetingJob($sessionMeeting->id);
        $this->app->call([$job, 'handle']);

        $sessionMeeting->refresh();
        $this->assertSame(MeetingStatus::Failed, $sessionMeeting->status);
        $this->assertSame('The tutor has not selected a meeting provider.', $sessionMeeting->metadata['error'] ?? null);
    }

    public function test_provider_runtime_exception_marks_meeting_failed_without_propagating(): void
    {
        $tutor = $this->tutor();
        $this->connectGoogle($tutor);
        $tutor->update(['meeting_provider' => 'google']);
        [$session] = $this->sessionWithBooking($tutor);
        $sessionMeeting = $this->pendingMeetingFor($session);

        $this->bindFakeProvider(function () {
            throw new RuntimeException('The tutor has not connected a Google account.');
        });

        $job = new CreateSessionMeetingJob($sessionMeeting->id);
        $this->app->call([$job, 'handle']);

        $sessionMeeting->refresh();
        $this->assertSame(MeetingStatus::Failed, $sessionMeeting->status);
    }

    public function test_transient_provider_exception_propagates_so_the_queue_can_retry(): void
    {
        $tutor = $this->tutor();
        $this->connectGoogle($tutor);
        $tutor->update(['meeting_provider' => 'google']);
        [$session] = $this->sessionWithBooking($tutor);
        $sessionMeeting = $this->pendingMeetingFor($session);

        // Simulate a genuine transient failure (network error) rather than
        // a permanent precondition failure — MeetingService only swallows
        // RuntimeException, so use a different throwable to prove it leaks.
        $this->bindFakeProvider(function () {
            throw new \Exception('Temporary Google API outage.');
        });

        $job = new CreateSessionMeetingJob($sessionMeeting->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Temporary Google API outage.');

        $this->app->call([$job, 'handle']);
    }

    public function test_job_is_a_no_op_for_an_already_scheduled_meeting(): void
    {
        $tutor = $this->tutor();
        [$session] = $this->sessionWithBooking($tutor);
        $sessionMeeting = SessionMeeting::create([
            'teaching_session_id' => $session->id,
            'provider' => 'google',
            'meeting_url' => 'https://meet.google.com/already-scheduled',
            'status' => MeetingStatus::Scheduled,
        ]);

        // A provider bound to throw proves it is never invoked once the
        // meeting is already Scheduled — handle() must short-circuit first.
        $this->app->bind(GoogleCalendarMeetingProvider::class, fn () => new class implements MeetingProviderContract
        {
            public function createMeeting($session, $booking): array
            {
                throw new \Exception('This should never be called.');
            }

            public function deleteMeeting($session, $meeting): void {}
        });

        $job = new CreateSessionMeetingJob($sessionMeeting->id);
        $this->app->call([$job, 'handle']);

        $sessionMeeting->refresh();
        $this->assertSame(MeetingStatus::Scheduled, $sessionMeeting->status);
        $this->assertSame('https://meet.google.com/already-scheduled', $sessionMeeting->meeting_url);
    }
}
