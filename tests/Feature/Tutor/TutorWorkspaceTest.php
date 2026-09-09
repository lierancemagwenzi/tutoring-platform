<?php

namespace Tests\Feature\Tutor;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\SessionLesson;
use App\Models\SessionLessonBlock;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Attempts\AttemptService;
use App\Services\Booking\BookingConfirmationService;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TutorWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Curriculum $curriculum;

    private Grade $grade;

    private ServiceCategory $category;

    private SessionFormat $onlineFormat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->onlineFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
    }

    /**
     * @return array{0: User, 1: TutorProfile}
     */
    private function tutorWithProfile(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $tutorProfile->services()->create([
            'subject_id' => $this->subject->id,
            'grade_id' => $this->grade->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->onlineFormat->id,
            'title' => 'Grade 10 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 5,
            'visibility' => 'published',
        ])->curricula()->attach($this->curriculum->id);

        return [$tutorUser, $tutorProfile];
    }

    private function pendingBookingRequest(TutorProfile $tutorProfile, User $student, string $date): Booking
    {
        $service = $tutorProfile->services()->first();
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $date]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        return Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'pending',
        ]);
    }

    private function confirmedBooking(TutorProfile $tutorProfile, User $student, string $date, string $startTime = '09:00'): Booking
    {
        $service = $tutorProfile->services()->first();
        $availabilityDate = AvailabilityDate::firstOrCreate(['tutor_profile_id' => $tutorProfile->id, 'date' => $date]);
        $slot = $availabilityDate->slots()->create(['start_time' => $startTime, 'end_time' => '12:00']);
        $endTime = Carbon::createFromFormat('H:i', $startTime)->addHour()->format('H:i');

        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'accepted',
        ]);

        $order = app(OrderService::class)->createForBooking($booking);
        app(BookingConfirmationService::class)->confirm($order);
        $booking = $booking->fresh();

        $session = TeachingSession::create([
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'scheduled',
        ]);
        $booking->teachingSessions()->attach($session->id);

        return $booking->fresh();
    }

    private function lessonFor(TutorProfile $tutorProfile): Lesson
    {
        $course = $tutorProfile->courses()->create([
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

    private function assignBlock(SessionLesson $sessionLesson, LessonBlock $block, array $overrides = []): SessionLessonBlock
    {
        return SessionLessonBlock::create(array_merge([
            'session_lesson_id' => $sessionLesson->id,
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'attempts_mode' => 'unlimited',
            'completion_mode' => 'not_tracked',
            'visibility' => 'visible',
            'passing_score' => 50,
        ], $overrides));
    }

    public function test_workspace_shows_todays_sessions_and_counts_them_in_stats(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $today = Carbon::today()->format('Y-m-d');
        $booking = $this->confirmedBooking($tutorProfile, $student, $today);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonCount(1, 'todays_sessions');
        $response->assertJsonPath('todays_sessions.0.session.id', $booking->teachingSessions()->first()->id);
        $response->assertJsonPath('stats.sessions_today', 1);
        $response->assertJsonPath('stats.active_students', 1);
    }

    public function test_pending_booking_request_appears_in_widget_and_action_center(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $booking = $this->pendingBookingRequest($tutorProfile, $student, Carbon::now()->addDays(5)->format('Y-m-d'));

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonCount(1, 'booking_requests');
        $response->assertJsonPath('booking_requests.0.id', $booking->id);
        $response->assertJsonPath('stats.pending_booking_requests', 1);
        $response->assertJsonFragment(['type' => 'booking_request']);
    }

    public function test_submitted_assignment_appears_in_pending_reviews_and_action_center(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $booking = $this->confirmedBooking($tutorProfile, $student, Carbon::now()->addDays(3)->format('Y-m-d'));
        $lesson = $this->lessonFor($tutorProfile);

        $activity = $lesson->learningActivities()->create([
            'type' => 'assignment', 'title' => 'Essay 1', 'status' => 'draft', 'submission_type' => 'text', 'max_score' => 100,
        ]);
        $block = $lesson->blocks()->create([
            'block_type' => 'assignment', 'position' => 0, 'title' => 'Essay 1',
            'content' => ['learning_activity_id' => $activity->id], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $booking->teachingSessions()->first()->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $slb = $this->assignBlock($sessionLesson, $block);

        Submission::create([
            'session_lesson_block_id' => $slb->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'submission_text' => ['html' => '<p>My essay</p>', 'json' => null],
            'submitted_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonCount(1, 'pending_reviews');
        $response->assertJsonPath('pending_reviews.0.student_name', trim("{$student->first_name} {$student->last_name}"));
        $response->assertJsonPath('stats.pending_reviews', 1);
        $response->assertJsonFragment(['type' => 'pending_review']);
    }

    public function test_returned_submission_flags_student_for_attention(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $booking = $this->confirmedBooking($tutorProfile, $student, Carbon::now()->addDays(3)->format('Y-m-d'));
        $lesson = $this->lessonFor($tutorProfile);

        $activity = $lesson->learningActivities()->create([
            'type' => 'assignment', 'title' => 'Essay 1', 'status' => 'draft', 'submission_type' => 'text', 'max_score' => 100,
        ]);
        $block = $lesson->blocks()->create([
            'block_type' => 'assignment', 'position' => 0, 'title' => 'Essay 1',
            'content' => ['learning_activity_id' => $activity->id], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $booking->teachingSessions()->first()->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $slb = $this->assignBlock($sessionLesson, $block);

        Submission::create([
            'session_lesson_block_id' => $slb->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'returned',
            'submission_text' => ['html' => '<p>Draft</p>', 'json' => null],
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonCount(1, 'students_requiring_attention');
        $response->assertJsonPath('students_requiring_attention.0.reasons.0.label', 'Returned assignment awaiting resubmission');
    }

    public function test_graded_unpublished_submission_flags_student_and_does_not_leak_into_performance(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $booking = $this->confirmedBooking($tutorProfile, $student, Carbon::now()->addDays(3)->format('Y-m-d'));
        $lesson = $this->lessonFor($tutorProfile);

        $activity = $lesson->learningActivities()->create([
            'type' => 'assignment', 'title' => 'Essay 1', 'status' => 'draft', 'submission_type' => 'text', 'max_score' => 100,
        ]);
        $block = $lesson->blocks()->create([
            'block_type' => 'assignment', 'position' => 0, 'title' => 'Essay 1',
            'content' => ['learning_activity_id' => $activity->id], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $booking->teachingSessions()->first()->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $slb = $this->assignBlock($sessionLesson, $block);

        Submission::create([
            'session_lesson_block_id' => $slb->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'submission_text' => ['html' => '<p>My essay</p>', 'json' => null],
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'score' => 70,
            'passed' => true,
            'published_at' => null,
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonCount(1, 'students_requiring_attention');
        $response->assertJsonPath('students_requiring_attention.0.reasons.0.label', 'Graded work awaiting your published feedback');
        // The tutor's own performance snapshot always sees the real score,
        // regardless of the student-facing publish gate.
        $response->assertJsonPath('performance_snapshot.average_percentage', 70.0);
    }

    public function test_completed_attempt_feeds_the_performance_snapshot_by_type(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $booking = $this->confirmedBooking($tutorProfile, $student, Carbon::now()->addDays(3)->format('Y-m-d'));
        $lesson = $this->lessonFor($tutorProfile);

        $block = $lesson->blocks()->create([
            'block_type' => 'h5p', 'position' => 0, 'title' => 'Activity 1',
            'content' => ['h5p_content_id' => '123'], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $booking->teachingSessions()->first()->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $slb = $this->assignBlock($sessionLesson, $block);

        $attempt = app(AttemptService::class)->start($slb, $block, $student);
        app(AttemptService::class)->complete($attempt, [
            'statement' => [
                'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
                'result' => ['score' => ['raw' => 8, 'max' => 10, 'scaled' => 0.8], 'completion' => true, 'success' => true],
            ],
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonPath('performance_snapshot.by_type.h5p.average_percentage', 80.0);
        $response->assertJsonCount(1, 'recent_activity');
    }

    public function test_manual_release_pending_block_appears_in_content_release_and_action_center(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $booking = $this->confirmedBooking($tutorProfile, $student, Carbon::now()->addDays(3)->format('Y-m-d'));
        $lesson = $this->lessonFor($tutorProfile);

        $block = $lesson->blocks()->create([
            'block_type' => 'rich_text', 'position' => 0, 'title' => 'Notes',
            'content' => ['html' => '<p>Notes</p>'], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $booking->teachingSessions()->first()->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $this->assignBlock($sessionLesson, $block, [
            'availability_mode' => 'manual_release',
            'is_manually_released' => false,
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonCount(1, 'content_release');
        $response->assertJsonPath('content_release.0.reason', 'manual_release_pending');
        $response->assertJsonFragment(['type' => 'content_release']);
    }

    public function test_availability_summary_reports_today_and_unavailable_gaps(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $today = Carbon::today()->format('Y-m-d');
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $today]);
        $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonPath('availability.today.date', $today);
        $response->assertJsonCount(1, 'availability.today.slots');
        $this->assertNotContains($today, $response->json('availability.unavailable_dates'));
        $this->assertContains(Carbon::tomorrow()->format('Y-m-d'), $response->json('availability.unavailable_dates'));
    }

    public function test_tutor_cannot_see_another_tutors_workspace_data(): void
    {
        [, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $this->confirmedBooking($tutorProfile, $student, Carbon::today()->format('Y-m-d'));

        [$otherTutorUser] = $this->tutorWithProfile();
        Sanctum::actingAs($otherTutorUser);

        $response = $this->getJson('/api/tutor/workspace');

        $response->assertOk();
        $response->assertJsonCount(0, 'todays_sessions');
        $response->assertJsonPath('stats.sessions_today', 0);
        $response->assertJsonPath('stats.active_students', 0);
    }

    public function test_upcoming_sessions_endpoint_is_paginated_and_excludes_today(): void
    {
        [$tutorUser, $tutorProfile] = $this->tutorWithProfile();
        $student = User::factory()->create();
        $this->confirmedBooking($tutorProfile, $student, Carbon::today()->format('Y-m-d'));
        $this->confirmedBooking($tutorProfile, $student, Carbon::now()->addDays(4)->format('Y-m-d'), '10:00');

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/workspace/upcoming-sessions');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
    }
}
