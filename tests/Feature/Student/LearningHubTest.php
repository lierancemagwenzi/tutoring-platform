<?php

namespace Tests\Feature\Student;

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

class LearningHubTest extends TestCase
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
     * @return array{0: User, 1: Booking, 2: Lesson, 3: TeachingSession}
     */
    private function bookedSessionFor(User $student, string $date): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $service = $tutorProfile->services()->create([
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
            'max_students_per_session' => 2,
            'visibility' => 'published',
        ]);
        $service->curricula()->attach($this->curriculum->id);

        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $date]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'accepted',
        ]);
        $order = app(OrderService::class)->createForBooking($booking);
        app(BookingConfirmationService::class)->confirm($order);
        $booking = $booking->fresh();

        // Auto-scheduled by BookingConfirmationService from the booking's
        // own requested date/time.
        $session = $booking->teachingSessions()->firstOrFail();

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
        $lesson = $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'published']);

        return [$tutorUser, $booking->fresh(), $lesson, $session];
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

    public function test_hub_shows_todays_sessions_and_counts_them_in_stats(): void
    {
        $student = User::factory()->create();
        $today = Carbon::today()->format('Y-m-d');
        [, $booking, , $session] = $this->bookedSessionFor($student, $today);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/learning-hub');

        $response->assertOk();
        $response->assertJsonCount(1, 'todays_sessions');
        $response->assertJsonPath('todays_sessions.0.session.id', $session->id);
        $response->assertJsonPath('todays_sessions.0.booking_id', $booking->id);
        $response->assertJsonPath('stats.sessions_today', 1);
    }

    public function test_hub_shows_a_not_started_activity_under_continue_learning_and_pending(): void
    {
        $student = User::factory()->create();
        $futureDate = Carbon::now()->addDays(5)->format('Y-m-d');
        [, $booking, $lesson, $session] = $this->bookedSessionFor($student, $futureDate);

        $block = $lesson->blocks()->create([
            'block_type' => 'h5p',
            'position' => 0,
            'title' => 'Interactive Activity',
            'content' => ['h5p_content_id' => '123'],
            'settings' => [],
            'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $session->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $this->assignBlock($sessionLesson, $block);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/learning-hub');

        $response->assertOk();
        $response->assertJsonCount(1, 'continue_learning');
        $response->assertJsonPath('continue_learning.0.title', 'Interactive Activity');
        $response->assertJsonPath('continue_learning.0.status', null);
        $response->assertJsonPath('continue_learning.0.url', "/student/bookings/{$booking->id}/lesson-blocks/{$block->id}");
        $response->assertJsonCount(1, 'pending_activities');
        $response->assertJsonPath('stats.pending_activities', 1);
    }

    public function test_hub_hides_unavailable_blocks_from_continue_learning(): void
    {
        $student = User::factory()->create();
        [, $booking, $lesson, $session] = $this->bookedSessionFor($student, Carbon::now()->addDays(5)->format('Y-m-d'));

        $block = $lesson->blocks()->create([
            'block_type' => 'h5p', 'position' => 0, 'title' => 'Hidden Activity',
            'content' => ['h5p_content_id' => '123'], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $session->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $this->assignBlock($sessionLesson, $block, ['availability_mode' => 'manual_release', 'is_manually_released' => false]);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/learning-hub');

        $response->assertOk();
        $response->assertJsonCount(0, 'continue_learning');
    }

    public function test_hub_shows_a_graded_published_result_and_feedback_with_correct_percentage(): void
    {
        $student = User::factory()->create();
        [$tutorUser, $booking, $lesson, $session] = $this->bookedSessionFor($student, Carbon::now()->addDays(5)->format('Y-m-d'));

        $activity = $lesson->learningActivities()->create([
            'type' => 'assignment', 'title' => 'Essay 1', 'status' => 'draft', 'submission_type' => 'text', 'max_score' => 100,
        ]);
        $block = $lesson->blocks()->create([
            'block_type' => 'assignment', 'position' => 0, 'title' => 'Essay 1',
            'content' => ['learning_activity_id' => $activity->id], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $session->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $slb = $this->assignBlock($sessionLesson, $block, ['passing_score' => 50]);

        Submission::create([
            'session_lesson_block_id' => $slb->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'submission_text' => ['html' => '<p>Done</p>', 'json' => null],
            'submitted_at' => now()->subDay(),
            'score' => 75,
            'passed' => true,
            'reviewed_at' => now()->subHours(2),
            'reviewed_by' => $tutorUser->id,
            'feedback_text' => ['html' => '<p>Great work!</p>', 'json' => null],
            'published_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/learning-hub');

        $response->assertOk();
        $response->assertJsonCount(1, 'recent_results');
        $response->assertJsonPath('recent_results.0.percentage', 75.0);
        $response->assertJsonPath('recent_results.0.passed', true);
        $response->assertJsonPath('performance_snapshot.average_percentage', 75.0);
        $response->assertJsonPath('performance_snapshot.activities_completed', 1);
        $response->assertJsonCount(1, 'latest_feedback');
        $response->assertJsonPath('latest_feedback.0.tutor', trim("{$tutorUser->first_name} {$tutorUser->last_name}"));
        $response->assertJsonPath('latest_feedback.0.feedback', '<p>Great work!</p>');
    }

    public function test_unpublished_grade_is_not_leaked_to_the_student(): void
    {
        $student = User::factory()->create();
        [$tutorUser, $booking, $lesson, $session] = $this->bookedSessionFor($student, Carbon::now()->addDays(5)->format('Y-m-d'));

        $activity = $lesson->learningActivities()->create([
            'type' => 'assignment', 'title' => 'Essay 1', 'status' => 'draft', 'submission_type' => 'text', 'max_score' => 100,
        ]);
        $block = $lesson->blocks()->create([
            'block_type' => 'assignment', 'position' => 0, 'title' => 'Essay 1',
            'content' => ['learning_activity_id' => $activity->id], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $session->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $slb = $this->assignBlock($sessionLesson, $block);

        Submission::create([
            'session_lesson_block_id' => $slb->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'submission_text' => ['html' => '<p>Done</p>', 'json' => null],
            'submitted_at' => now()->subDay(),
            'score' => 90,
            'passed' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $tutorUser->id,
            'feedback_text' => ['html' => '<p>Nice.</p>', 'json' => null],
            'published_at' => null,
        ]);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/learning-hub');

        $response->assertOk();
        $response->assertJsonCount(0, 'recent_results');
        $response->assertJsonCount(0, 'latest_feedback');
        $response->assertJsonPath('performance_snapshot.activities_completed', 0);
    }

    public function test_returned_submission_counts_as_awaiting_revision_and_appears_in_pending_and_results(): void
    {
        $student = User::factory()->create();
        [$tutorUser, $booking, $lesson, $session] = $this->bookedSessionFor($student, Carbon::now()->addDays(5)->format('Y-m-d'));

        $activity = $lesson->learningActivities()->create([
            'type' => 'assignment', 'title' => 'Essay 1', 'status' => 'draft', 'submission_type' => 'text', 'max_score' => 100,
        ]);
        $block = $lesson->blocks()->create([
            'block_type' => 'assignment', 'position' => 0, 'title' => 'Essay 1',
            'content' => ['learning_activity_id' => $activity->id], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $session->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $slb = $this->assignBlock($sessionLesson, $block);

        Submission::create([
            'session_lesson_block_id' => $slb->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'returned',
            'submission_text' => ['html' => '<p>Draft</p>', 'json' => null],
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'reviewed_by' => $tutorUser->id,
        ]);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/learning-hub');

        $response->assertOk();
        $response->assertJsonPath('stats.awaiting_revision', 1);
        $response->assertJsonPath('pending_activities.0.status', 'returned');
        $response->assertJsonPath('recent_results.0.status', 'returned');
    }

    public function test_a_completed_attempt_feeds_the_performance_snapshot_by_type(): void
    {
        $student = User::factory()->create();
        [, $booking, $lesson, $session] = $this->bookedSessionFor($student, Carbon::now()->addDays(5)->format('Y-m-d'));

        $block = $lesson->blocks()->create([
            'block_type' => 'h5p', 'position' => 0, 'title' => 'Interactive Activity',
            'content' => ['h5p_content_id' => '123'], 'settings' => [], 'status' => 'published',
        ]);
        $sessionLesson = SessionLesson::create(['teaching_session_id' => $session->id, 'lesson_id' => $lesson->id, 'position' => 0]);
        $slb = $this->assignBlock($sessionLesson, $block, ['passing_score' => 5]);

        $attempt = app(AttemptService::class)->start($slb, $block, $student);
        app(AttemptService::class)->complete($attempt, [
            'statement' => ['verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'], 'result' => ['score' => ['raw' => 8, 'max' => 10, 'scaled' => 0.8], 'completion' => true]],
        ]);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/learning-hub');

        $response->assertOk();
        $response->assertJsonPath('performance_snapshot.by_type.h5p.average_percentage', 80.0);
        $response->assertJsonCount(1, 'recent_results');
    }

    public function test_student_cannot_see_another_students_hub_data(): void
    {
        $studentA = User::factory()->create();
        $studentB = User::factory()->create();
        $this->bookedSessionFor($studentA, Carbon::today()->format('Y-m-d'));

        Sanctum::actingAs($studentB);
        $response = $this->getJson('/api/student/learning-hub');

        $response->assertOk();
        $response->assertJsonCount(0, 'todays_sessions');
        $response->assertJsonPath('stats.sessions_today', 0);
    }

    public function test_upcoming_sessions_endpoint_is_paginated_and_excludes_today(): void
    {
        $student = User::factory()->create();
        $this->bookedSessionFor($student, Carbon::today()->format('Y-m-d'));
        $this->bookedSessionFor($student, Carbon::now()->addDays(3)->format('Y-m-d'));

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/learning-hub/upcoming-sessions?per_page=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'sessions');
        $response->assertJsonPath('meta.total', 1);
    }
}
