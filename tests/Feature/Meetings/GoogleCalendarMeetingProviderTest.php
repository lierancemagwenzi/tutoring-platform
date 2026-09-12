<?php

namespace Tests\Feature\Meetings;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorConnectedAccount;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Meetings\GoogleCalendarMeetingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * A 403 from Google's Calendar API means the connected account's access
 * token lacks Calendar scope — see GoogleProvider::exchangeCode(), which
 * now blocks this for new connections, but an account connected before
 * that check existed can still hit it. This is a permanent failure (no
 * amount of retrying grants the scope back), unlike a genuine transient
 * error, which must still propagate for the queue's normal retry.
 */
class GoogleCalendarMeetingProviderTest extends TestCase
{
    use RefreshDatabase;

    private function tutorWithConnectedAccount(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        TutorConnectedAccount::create([
            'tutor_profile_id' => $tutor->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
            'email' => 'tutor@gmail.com',
            'access_token' => 'stale-token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        return $tutor;
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

    public function test_a_403_from_google_calendar_becomes_a_permanent_runtime_exception(): void
    {
        $tutor = $this->tutorWithConnectedAccount();
        [$session, $booking] = $this->sessionWithBooking($tutor);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/*' => Http::response([
                'error' => ['code' => 403, 'message' => 'Request had insufficient authentication scopes.'],
            ], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("doesn't have calendar permission");

        app(GoogleCalendarMeetingProvider::class)->createMeeting($session->fresh(['tutorProfile.user', 'service.sessionFormat', 'bookings.student']), $booking);
    }

    public function test_a_403_with_accessnotconfigured_becomes_an_api_not_enabled_exception(): void
    {
        $tutor = $this->tutorWithConnectedAccount();
        [$session, $booking] = $this->sessionWithBooking($tutor);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/*' => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'Google Calendar API has not been used in project 123 before or it is disabled.',
                    'errors' => [
                        ['reason' => 'accessNotConfigured', 'domain' => 'usageLimits'],
                    ],
                    'status' => 'PERMISSION_DENIED',
                ],
            ], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Calendar API is not enabled');

        app(GoogleCalendarMeetingProvider::class)->createMeeting($session->fresh(['tutorProfile.user', 'service.sessionFormat', 'bookings.student']), $booking);
    }

    public function test_fake_meetings_flag_skips_google_entirely_and_returns_a_placeholder_link(): void
    {
        config(['services.google.fake_meetings' => true]);

        // No connected account at all — the fake path must not need one.
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$session, $booking] = $this->sessionWithBooking($tutor);

        Http::fake(function () {
            $this->fail('The real Google Calendar API must never be called while fake_meetings is enabled.');
        });

        $result = app(GoogleCalendarMeetingProvider::class)->createMeeting(
            $session->fresh(['tutorProfile.user', 'service.sessionFormat', 'bookings.student']),
            $booking,
        );

        $this->assertStringStartsWith('https://meet.google.com/fake-', $result['meeting_url']);
        $this->assertTrue($result['metadata']['fake']);
    }

    public function test_a_transient_google_calendar_error_still_propagates_for_retry(): void
    {
        $tutor = $this->tutorWithConnectedAccount();
        [$session, $booking] = $this->sessionWithBooking($tutor);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/*' => Http::response(['error' => ['code' => 500]], 500),
        ]);

        $this->expectException(RequestException::class);

        app(GoogleCalendarMeetingProvider::class)->createMeeting($session->fresh(['tutorProfile.user', 'service.sessionFormat', 'bookings.student']), $booking);
    }
}
