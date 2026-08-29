<?php

namespace Tests\Feature\Tutor;

use App\Models\Attempt;
use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\SessionLesson;
use App\Models\SessionLessonBlock;
use App\Models\Subject;
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

class AttemptTest extends TestCase
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

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->onlineFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $this->futureDate = Carbon::now()->addDays(10)->format('Y-m-d');
    }

    /**
     * @return array{0: User, 1: User, 2: SessionLessonBlock, 3: Attempt}
     */
    private function createDeliveryWithCompletedAttempt(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

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

        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $this->futureDate]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        $studentUser = User::factory()->create();

        $booking = Booking::create([
            'student_id' => $studentUser->id,
            'tutor_profile_id' => $tutorProfile->id,
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

        $session = TeachingSession::create([
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);
        $booking->teachingSessions()->attach($session->id);

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

        $block = $lesson->blocks()->create([
            'block_type' => 'h5p',
            'position' => 0,
            'title' => 'Activity 1',
            'content' => ['h5p_content_id' => '123'],
            'settings' => [],
            'status' => 'published',
        ]);

        $sessionLesson = SessionLesson::create([
            'teaching_session_id' => $session->id,
            'lesson_id' => $lesson->id,
            'position' => 0,
        ]);

        $sessionLessonBlock = SessionLessonBlock::create([
            'session_lesson_id' => $sessionLesson->id,
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'attempts_mode' => 'unlimited',
            'completion_mode' => 'not_tracked',
            'visibility' => 'visible',
            'passing_score' => 5,
        ]);

        $attempt = app(AttemptService::class)->start($sessionLessonBlock, $block, $studentUser);
        app(AttemptService::class)->complete($attempt, [
            'statement' => [
                'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
                'result' => ['score' => ['raw' => 8, 'max' => 10, 'scaled' => 0.8], 'completion' => true, 'success' => true],
            ],
        ]);

        return [$tutorUser, $studentUser, $sessionLessonBlock, $attempt->fresh()];
    }

    public function test_tutor_can_list_attempts_for_a_delivery_instance(): void
    {
        [$tutor, , $slb, $attempt] = $this->createDeliveryWithCompletedAttempt();
        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/session-lesson-blocks/{$slb->id}/attempts");

        $response->assertOk();
        $response->assertJsonCount(1, 'attempts');
        $response->assertJsonPath('attempts.0.id', $attempt->id);
        $response->assertJsonPath('attempts.0.student.id', $attempt->student_id);
        $response->assertJsonPath('attempts.0.percentage', '80.00');
    }

    public function test_tutor_can_view_a_single_attempt_with_the_raw_provider_result(): void
    {
        [$tutor, , , $attempt] = $this->createDeliveryWithCompletedAttempt();
        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/attempts/{$attempt->id}");

        $response->assertOk();
        $response->assertJsonPath('attempt.raw_provider_response.statement.result.score.raw', 8);
        $response->assertJsonPath('attempt.provider_metadata.h5p_success', true);
    }

    public function test_tutor_cannot_manage_another_tutors_attempts(): void
    {
        [, , $slb, $attempt] = $this->createDeliveryWithCompletedAttempt();
        $otherTutor = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $otherTutor->id, 'display_name' => 'Other Tutor']);
        Sanctum::actingAs($otherTutor);

        $this->getJson("/api/tutor/session-lesson-blocks/{$slb->id}/attempts")->assertForbidden();
        $this->getJson("/api/tutor/attempts/{$attempt->id}")->assertForbidden();
    }
}
