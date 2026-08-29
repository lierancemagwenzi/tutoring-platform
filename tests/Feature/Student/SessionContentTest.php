<?php

namespace Tests\Feature\Student;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\LessonBlock;
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
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionContentTest extends TestCase
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
     * @return array{0: User, 1: Booking}
     */
    private function createBookedSession(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $service = $tutorProfile->services()->create([
            'subject_id' => $this->subject->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->onlineFormat->id,
            'title' => 'Grade 12 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 2,
            'visibility' => 'published',
        ]);

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

        $sessionLesson = SessionLesson::create([
            'teaching_session_id' => $session->id,
            'lesson_id' => $lesson->id,
            'position' => 0,
        ]);

        $this->makeBlock($sessionLesson, 'Always Available Block', ['availability_mode' => 'always_available']);
        $this->makeBlock($sessionLesson, 'Unassigned Block', null); // never assigned at all
        $this->makeBlock($sessionLesson, 'Future Scheduled Block', [
            'availability_mode' => 'scheduled_release',
            'available_from' => Carbon::now()->addDay(),
        ]);
        $this->makeBlock($sessionLesson, 'Manually Hidden Block', [
            'availability_mode' => 'manual_release',
            'is_manually_released' => false,
        ]);
        $this->makeBlock($sessionLesson, 'Expired Assessment Block', [
            'availability_mode' => 'assessment_window',
            'opens_at' => Carbon::now()->subHours(3),
            'closes_at' => Carbon::now()->subHour(),
        ]);

        return [$studentUser, $booking->fresh(), $sessionLesson];
    }

    private function makeBlock(SessionLesson $sessionLesson, string $title, ?array $assignment): LessonBlock
    {
        $block = $sessionLesson->lesson->blocks()->create([
            'block_type' => 'rich_text',
            'position' => $sessionLesson->lesson->blocks()->count(),
            'title' => $title,
            'content' => ['html' => "<p>{$title}</p>"],
            'settings' => [],
            'status' => 'published',
        ]);

        if ($assignment !== null) {
            SessionLessonBlock::create(array_merge([
                'session_lesson_id' => $sessionLesson->id,
                'lesson_block_id' => $block->id,
                'availability_mode' => 'always_available',
            ], $assignment));
        }

        return $block;
    }

    private function makeH5pBlock(SessionLesson $sessionLesson, string $title, ?array $assignment): LessonBlock
    {
        $block = $sessionLesson->lesson->blocks()->create([
            'block_type' => 'h5p',
            'position' => $sessionLesson->lesson->blocks()->count(),
            'title' => $title,
            'content' => ['h5p_content_id' => '123'],
            'settings' => [],
            'status' => 'published',
        ]);

        if ($assignment !== null) {
            SessionLessonBlock::create(array_merge([
                'session_lesson_id' => $sessionLesson->id,
                'lesson_block_id' => $block->id,
                'availability_mode' => 'always_available',
            ], $assignment));
        }

        return $block;
    }

    private function makeQuizBlock(SessionLesson $sessionLesson, string $title, ?array $assignment): LessonBlock
    {
        $quiz = $sessionLesson->lesson->quizzes()->create([
            'title' => $title,
            'status' => 'published',
            'settings' => [],
        ]);
        $quiz->questions()->create([
            'position' => 0,
            'type' => 'radiogroup',
            'definition' => ['title' => 'What is 2+2?', 'choices' => ['3', '4', '5'], 'correctAnswer' => '4'],
            'points' => 1,
        ]);

        $block = $sessionLesson->lesson->blocks()->create([
            'block_type' => 'quiz',
            'position' => $sessionLesson->lesson->blocks()->count(),
            'title' => $title,
            'content' => ['quiz_id' => $quiz->id],
            'settings' => [],
            'status' => 'published',
        ]);

        if ($assignment !== null) {
            SessionLessonBlock::create(array_merge([
                'session_lesson_id' => $sessionLesson->id,
                'lesson_block_id' => $block->id,
                'availability_mode' => 'always_available',
            ], $assignment));
        }

        return $block;
    }

    public function test_student_does_not_see_quiz_correct_answers(): void
    {
        [$studentUser, $booking, $sessionLesson] = $this->createBookedSession();
        $block = $this->makeQuizBlock($sessionLesson, 'Arithmetic Quiz', ['availability_mode' => 'always_available']);
        Sanctum::actingAs($studentUser);

        $index = $this->getJson("/api/bookings/{$booking->id}/lessons");
        $index->assertOk();
        $quizFromIndex = collect($index->json('lessons.0.blocks'))->firstWhere('title', 'Arithmetic Quiz');
        $this->assertArrayNotHasKey('correct_answer', $quizFromIndex['quiz']['questions'][0]);

        $show = $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$block->id}");
        $show->assertOk();
        $this->assertArrayNotHasKey('correct_answer', $show->json('block.quiz.questions.0'));
    }

    public function test_student_only_sees_currently_available_blocks(): void
    {
        [$studentUser, $booking] = $this->createBookedSession();
        Sanctum::actingAs($studentUser);

        $response = $this->getJson("/api/bookings/{$booking->id}/lessons");

        $response->assertOk();
        $response->assertJsonCount(1, 'lessons');
        $response->assertJsonCount(1, 'lessons.0.blocks');
        $response->assertJsonPath('lessons.0.blocks.0.title', 'Always Available Block');
    }

    public function test_student_sees_manually_released_block_once_released(): void
    {
        [$studentUser, $booking] = $this->createBookedSession();

        $hiddenBlock = LessonBlock::where('title', 'Manually Hidden Block')->firstOrFail();
        SessionLessonBlock::where('lesson_block_id', $hiddenBlock->id)->update(['is_manually_released' => true]);

        Sanctum::actingAs($studentUser);
        $response = $this->getJson("/api/bookings/{$booking->id}/lessons");

        $titles = collect($response->json('lessons.0.blocks'))->pluck('title');
        $this->assertTrue($titles->contains('Manually Hidden Block'));
    }

    public function test_student_cannot_view_another_students_booking_content(): void
    {
        [, $booking] = $this->createBookedSession();

        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/bookings/{$booking->id}/lessons")->assertForbidden();
    }

    public function test_student_can_view_a_single_available_block(): void
    {
        [$studentUser, $booking] = $this->createBookedSession();
        $block = LessonBlock::where('title', 'Always Available Block')->firstOrFail();

        Sanctum::actingAs($studentUser);
        $response = $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$block->id}");

        $response->assertOk();
        $response->assertJsonPath('block.title', 'Always Available Block');
    }

    public function test_student_cannot_view_a_block_that_is_not_currently_available(): void
    {
        [$studentUser, $booking] = $this->createBookedSession();
        $block = LessonBlock::where('title', 'Manually Hidden Block')->firstOrFail();

        Sanctum::actingAs($studentUser);
        $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$block->id}")->assertForbidden();
    }

    public function test_student_cannot_view_a_block_from_another_students_booking(): void
    {
        [, $booking] = $this->createBookedSession();
        $block = LessonBlock::where('title', 'Always Available Block')->firstOrFail();

        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$block->id}")->assertForbidden();
    }

    public function test_student_can_get_h5p_player_model_for_an_available_block(): void
    {
        [$studentUser, $booking, $sessionLesson] = $this->createBookedSession();
        $block = $this->makeH5pBlock($sessionLesson, 'Available H5P', ['availability_mode' => 'always_available']);

        Http::fake([
            '*/api/content/123/player-model' => Http::response(['contentId' => '123', 'dependencies' => []]),
        ]);

        Sanctum::actingAs($studentUser);
        $response = $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$block->id}/h5p-player-model");

        $response->assertOk();
        $response->assertJsonPath('contentId', '123');
    }

    public function test_student_cannot_get_h5p_player_model_for_an_unassigned_block(): void
    {
        [$studentUser, $booking, $sessionLesson] = $this->createBookedSession();
        $block = $this->makeH5pBlock($sessionLesson, 'Unassigned H5P', null);

        Sanctum::actingAs($studentUser);
        $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$block->id}/h5p-player-model")->assertForbidden();
    }

    public function test_student_cannot_get_h5p_player_model_for_another_students_booking(): void
    {
        [, $booking, $sessionLesson] = $this->createBookedSession();
        $block = $this->makeH5pBlock($sessionLesson, 'Available H5P', ['availability_mode' => 'always_available']);

        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$block->id}/h5p-player-model")->assertForbidden();
    }

    public function test_booking_without_a_session_yet_returns_no_lessons(): void
    {
        $studentUser = User::factory()->create();
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $tutorUser->id]);

        $service = $tutorProfile->services()->create([
            'subject_id' => $this->subject->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->onlineFormat->id,
            'title' => 'Grade 12 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 2,
            'visibility' => 'published',
        ]);
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $this->futureDate]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

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
            'status' => 'pending',
        ]);

        Sanctum::actingAs($studentUser);
        $response = $this->getJson("/api/bookings/{$booking->id}/lessons");

        $response->assertOk()->assertJsonCount(0, 'lessons');
    }
}
