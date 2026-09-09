<?php

namespace Tests\Feature\Tutor;

use App\Enums\MeetingStatus;
use App\Jobs\CreateSessionMeetingJob;
use App\Models\AvailabilityDate;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\SessionMeeting;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeachingSessionRetryMeetingTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function sessionFor(TutorProfile $tutor): TeachingSession
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

        return TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);
    }

    public function test_tutor_can_retry_a_failed_meeting(): void
    {
        Queue::fake();

        $tutor = $this->tutor();
        $session = $this->sessionFor($tutor);
        $meeting = SessionMeeting::create([
            'teaching_session_id' => $session->id,
            'status' => MeetingStatus::Failed,
        ]);

        Sanctum::actingAs($tutor->user);

        $response = $this->postJson("/api/tutor/sessions/{$session->id}/meeting/retry");

        $response->assertOk();
        Queue::assertPushed(CreateSessionMeetingJob::class, fn ($job) => $job->sessionMeetingId === $meeting->id);
    }

    public function test_retrying_an_already_scheduled_meeting_is_rejected(): void
    {
        Queue::fake();

        $tutor = $this->tutor();
        $session = $this->sessionFor($tutor);
        SessionMeeting::create([
            'teaching_session_id' => $session->id,
            'provider' => 'google',
            'status' => MeetingStatus::Scheduled,
        ]);

        Sanctum::actingAs($tutor->user);

        $response = $this->postJson("/api/tutor/sessions/{$session->id}/meeting/retry");

        $response->assertUnprocessable();
        Queue::assertNotPushed(CreateSessionMeetingJob::class);
    }

    public function test_retrying_a_session_with_no_meeting_returns_not_found(): void
    {
        $tutor = $this->tutor();
        $session = $this->sessionFor($tutor);

        Sanctum::actingAs($tutor->user);

        $response = $this->postJson("/api/tutor/sessions/{$session->id}/meeting/retry");

        $response->assertNotFound();
    }

    public function test_another_tutors_session_cannot_be_retried(): void
    {
        $owner = $this->tutor();
        $session = $this->sessionFor($owner);
        SessionMeeting::create([
            'teaching_session_id' => $session->id,
            'status' => MeetingStatus::Failed,
        ]);

        $intruder = $this->tutor();
        Sanctum::actingAs($intruder->user);

        $response = $this->postJson("/api/tutor/sessions/{$session->id}/meeting/retry");

        $response->assertForbidden();
    }
}
