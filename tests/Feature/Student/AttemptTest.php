<?php

namespace Tests\Feature\Student;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Quiz;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\SessionLesson;
use App\Models\SessionLessonBlock;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\User;
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

    /**
     * Creates a tutor + service + booked session, a block of the given type
     * assigned to that session with the given delivery configuration, and a
     * student who booked it. Returns [studentUser, booking, sessionLessonBlock, quiz|null].
     *
     * @return array{0: User, 1: Booking, 2: SessionLessonBlock, 3: ?Quiz}
     */
    private function createDelivery(string $blockType, array $sessionLessonBlockOverrides = []): array
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

        $quiz = null;
        if ($blockType === 'quiz') {
            $quiz = $lesson->quizzes()->create(['title' => 'Arithmetic Quiz', 'status' => 'published', 'settings' => []]);
            $quiz->questions()->create([
                'position' => 0,
                'type' => 'radiogroup',
                'definition' => ['title' => 'What is 2+2?', 'choices' => ['3', '4', '5'], 'correctAnswer' => '4'],
                'points' => 10,
            ]);
        }

        $block = $lesson->blocks()->create([
            'block_type' => $blockType,
            'position' => 0,
            'title' => 'Activity 1',
            'content' => $blockType === 'quiz' ? ['quiz_id' => $quiz->id] : ['h5p_content_id' => '123'],
            'settings' => [],
            'status' => 'published',
        ]);

        $sessionLesson = SessionLesson::create([
            'teaching_session_id' => $session->id,
            'lesson_id' => $lesson->id,
            'position' => 0,
        ]);

        $sessionLessonBlock = SessionLessonBlock::create(array_merge([
            'session_lesson_id' => $sessionLesson->id,
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'attempts_mode' => 'unlimited',
            'completion_mode' => 'not_tracked',
            'visibility' => 'visible',
            'passing_score' => 5,
        ], $sessionLessonBlockOverrides));

        return [$studentUser, $booking, $sessionLessonBlock, $quiz];
    }

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

    public function test_student_can_start_and_resume_an_attempt(): void
    {
        [$student, $booking, $slb] = $this->createDelivery('h5p');
        Sanctum::actingAs($student);

        $first = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts");
        $first->assertCreated();
        $first->assertJsonPath('attempt.status', 'started');
        $first->assertJsonPath('attempt.attempt_number', 1);
        $first->assertJsonPath('attempt.provider', 'h5p');

        $second = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts");
        $second->assertCreated();
        $second->assertJsonPath('attempt.id', $first->json('attempt.id'));
        $this->assertDatabaseCount('attempts', 1);
    }

    public function test_student_cannot_start_an_attempt_for_an_ineligible_block_type(): void
    {
        [$student, $booking, $slb] = $this->createDelivery('h5p');
        $slb->lessonBlock->update(['block_type' => 'rich_text']);
        Sanctum::actingAs($student);

        $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
    }

    public function test_student_cannot_start_an_attempt_when_the_block_is_unavailable(): void
    {
        [$student, $booking, $slb] = $this->createDelivery('h5p', ['availability_mode' => 'manual_release', 'is_manually_released' => false]);
        Sanctum::actingAs($student);

        $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
    }

    public function test_student_cannot_start_an_attempt_when_the_block_is_hidden(): void
    {
        [$student, $booking, $slb] = $this->createDelivery('h5p', ['visibility' => 'hidden']);
        Sanctum::actingAs($student);

        $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
    }

    public function test_student_cannot_exceed_the_maximum_number_of_attempts(): void
    {
        [$student, $booking, $slb] = $this->createDelivery('h5p', ['attempts_mode' => 'limited', 'max_attempts' => 1]);
        Sanctum::actingAs($student);

        $first = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")->json('attempt.id');
        $this->postJson("/api/attempts/{$first}/complete", [
            'raw_result' => ['result' => ['score' => ['raw' => 8, 'max' => 10, 'scaled' => 0.8], 'success' => true]],
        ])->assertOk();

        $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
    }

    public function test_student_can_mark_an_attempt_in_progress(): void
    {
        [$student, $booking, $slb] = $this->createDelivery('h5p');
        Sanctum::actingAs($student);

        $attemptId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")->json('attempt.id');

        $response = $this->patchJson("/api/attempts/{$attemptId}/in-progress");
        $response->assertOk();
        $response->assertJsonPath('attempt.status', 'in_progress');
    }

    public function test_student_can_complete_an_h5p_attempt_and_score_is_derived_from_the_xapi_statement(): void
    {
        [$student, $booking, $slb] = $this->createDelivery('h5p', ['passing_score' => 7]);
        Sanctum::actingAs($student);

        $attemptId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")->json('attempt.id');

        $response = $this->postJson("/api/attempts/{$attemptId}/complete", [
            'raw_result' => [
                'statement' => [
                    'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
                    'object' => ['id' => 'https://h5p.local/content/123'],
                    'result' => ['score' => ['raw' => 8, 'min' => 0, 'max' => 10, 'scaled' => 0.8], 'completion' => true, 'success' => true],
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('attempt.status', 'completed');
        $response->assertJsonPath('attempt.raw_score', '8.00');
        $response->assertJsonPath('attempt.max_score', '10.00');
        $response->assertJsonPath('attempt.percentage', '80.00');
        $response->assertJsonPath('attempt.passed', true);
        $this->assertNotNull($response->json('attempt.completed_at'));
        $this->assertNotNull($response->json('attempt.time_taken_seconds'));
    }

    public function test_student_can_complete_a_surveyjs_quiz_attempt_and_score_is_computed(): void
    {
        [$student, $booking, $slb, $quiz] = $this->createDelivery('quiz', ['passing_score' => 5]);
        Sanctum::actingAs($student);

        $question = $quiz->questions()->first();
        $attemptId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")->json('attempt.id');

        $response = $this->postJson("/api/attempts/{$attemptId}/complete", [
            'raw_result' => ["question_{$question->id}" => '4'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('attempt.raw_score', '10.00');
        $response->assertJsonPath('attempt.max_score', '10.00');
        $response->assertJsonPath('attempt.percentage', '100.00');
        $response->assertJsonPath('attempt.passed', true);
    }

    public function test_failing_score_is_not_marked_as_passed(): void
    {
        [$student, $booking, $slb, $quiz] = $this->createDelivery('quiz', ['passing_score' => 5]);
        Sanctum::actingAs($student);

        $question = $quiz->questions()->first();
        $attemptId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")->json('attempt.id');

        $response = $this->postJson("/api/attempts/{$attemptId}/complete", [
            'raw_result' => ["question_{$question->id}" => '3'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('attempt.raw_score', '0.00');
        $response->assertJsonPath('attempt.passed', false);
    }

    public function test_student_cannot_complete_another_students_attempt(): void
    {
        [$student, $booking, $slb] = $this->createDelivery('h5p');
        Sanctum::actingAs($student);
        $attemptId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/attempts")->json('attempt.id');

        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);

        $this->postJson("/api/attempts/{$attemptId}/complete", ['raw_result' => []])->assertForbidden();
        $this->patchJson("/api/attempts/{$attemptId}/in-progress")->assertForbidden();
    }
}
