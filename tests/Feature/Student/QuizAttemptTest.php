<?php

namespace Tests\Feature\Student;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuizAttemptTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Subject $otherSubject;

    private Curriculum $curriculum;

    private Curriculum $otherCurriculum;

    private Grade $grade;

    private Grade $otherGrade;

    private ServiceCategory $category;

    private SessionFormat $sessionFormat;

    private TutorProfile $tutorProfile;

    private Course $course;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->otherSubject = Subject::create(['name' => 'Physics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->otherCurriculum = Curriculum::create(['name' => 'Cambridge', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->otherGrade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->sessionFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);

        $tutorUser = User::factory()->tutor()->create();
        $this->tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id]);

        $this->course = $this->tutorProfile->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'A complete introduction to algebraic concepts.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'published',
        ]);
        $chapter = $this->course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'published']);
        $lesson = $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'published']);
        $this->quiz = $lesson->quizzes()->create(['title' => 'Chapter Check-in', 'status' => 'published', 'settings' => []]);
    }

    /**
     * @return array{0: QuizQuestion, 1: QuizQuestion, 2: QuizQuestion}
     */
    private function seedQuestions(): array
    {
        $singleChoice = $this->quiz->questions()->create([
            'position' => 0,
            'type' => 'radiogroup',
            'definition' => ['title' => 'What is 2 + 2?', 'choices' => ['3', '4', '5'], 'correctAnswer' => '4'],
            'points' => 2,
        ]);

        $multipleChoice = $this->quiz->questions()->create([
            'position' => 1,
            'type' => 'checkbox',
            'definition' => ['title' => 'Which are even?', 'choices' => ['1', '2', '3', '4'], 'correctAnswer' => ['2', '4']],
            'points' => 2,
        ]);

        $text = $this->quiz->questions()->create([
            'position' => 2,
            'type' => 'text',
            'definition' => ['title' => 'Explain your reasoning.'],
            'points' => 1,
        ]);

        return [$singleChoice, $multipleChoice, $text];
    }

    private function createService(array $overrides = []): Service
    {
        return $this->tutorProfile->services()->create(array_merge([
            'subject_id' => $this->subject->id,
            'grade_id' => $this->grade->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->sessionFormat->id,
            'title' => 'Algebra Tutoring',
            'description' => 'One-on-one algebra tutoring.',
            'price' => 350,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ], $overrides));
    }

    private function paidBookingFor(User $student, Service $service): void
    {
        $availabilityDate = AvailabilityDate::create([
            'tutor_profile_id' => $this->tutorProfile->id,
            'date' => now()->addDay()->toDateString(),
        ]);
        $slot = $availabilityDate->slots()->create(['start_time' => '10:00', 'end_time' => '11:00']);

        Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $this->tutorProfile->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'price' => $service->price,
            'currency' => 'ZAR',
            'status' => 'confirmed',
        ]);
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    public function test_student_without_a_paid_booking_cannot_view_the_quiz(): void
    {
        $student = $this->student();
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/quizzes/{$this->quiz->id}");

        $response->assertForbidden();
    }

    public function test_student_with_a_paid_booking_for_a_different_subject_cannot_view_the_quiz(): void
    {
        $student = $this->student();
        $service = $this->createService(['subject_id' => $this->otherSubject->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/quizzes/{$this->quiz->id}");

        $response->assertForbidden();
    }

    public function test_student_with_a_paid_booking_for_a_different_grade_cannot_view_the_quiz(): void
    {
        $student = $this->student();
        $service = $this->createService(['grade_id' => $this->otherGrade->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/quizzes/{$this->quiz->id}");

        $response->assertForbidden();
    }

    public function test_student_with_a_paid_booking_for_a_different_curriculum_cannot_view_the_quiz(): void
    {
        $student = $this->student();
        $service = $this->createService();
        $service->curricula()->sync([$this->otherCurriculum->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/quizzes/{$this->quiz->id}");

        $response->assertForbidden();
    }

    public function test_student_with_a_matching_paid_booking_can_view_the_quiz(): void
    {
        $student = $this->student();
        $service = $this->createService();
        $service->curricula()->sync([$this->curriculum->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/quizzes/{$this->quiz->id}");

        $response->assertOk()->assertJsonPath('quiz.title', 'Chapter Check-in');
    }

    public function test_student_with_a_paid_booking_for_a_grade_agnostic_service_can_view_the_quiz(): void
    {
        // A service with no grade requirement (grade_id null) must match a
        // course of any grade — grade_id on a course is never null, so a
        // naive equality check here would silently deny access.
        $student = $this->student();
        $service = $this->createService(['grade_id' => null]);
        $service->curricula()->sync([$this->curriculum->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/quizzes/{$this->quiz->id}");

        $response->assertOk()->assertJsonPath('quiz.title', 'Chapter Check-in');
    }

    public function test_unpublished_quiz_is_not_accessible_even_with_a_paid_booking(): void
    {
        $this->quiz->update(['status' => 'draft']);

        $student = $this->student();
        $service = $this->createService();
        $service->curricula()->sync([$this->curriculum->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/quizzes/{$this->quiz->id}");

        $response->assertForbidden();
    }

    public function test_student_cannot_start_an_attempt_without_a_matching_paid_booking(): void
    {
        $student = $this->student();
        Sanctum::actingAs($student);

        $response = $this->postJson("/api/quizzes/{$this->quiz->id}/attempts");

        $response->assertForbidden();
    }

    public function test_student_can_start_and_submit_an_attempt_with_correct_scoring(): void
    {
        [$singleChoice, $multipleChoice, $text] = $this->seedQuestions();

        $student = $this->student();
        $service = $this->createService();
        $service->curricula()->sync([$this->curriculum->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $start = $this->postJson("/api/quizzes/{$this->quiz->id}/attempts");
        $start->assertCreated();
        $attemptId = $start->json('attempt.id');

        $submit = $this->postJson("/api/quizzes/attempts/{$attemptId}/submit", [
            'answers' => [
                ['question_id' => $singleChoice->id, 'answer' => '4'],
                ['question_id' => $multipleChoice->id, 'answer' => ['4', '2']],
                ['question_id' => $text->id, 'answer' => 'Because arithmetic.'],
            ],
        ]);

        $submit->assertOk();
        $submit->assertJsonPath('attempt.status', 'completed');
        $submit->assertJsonPath('attempt.score', '4.00');
        $submit->assertJsonPath('attempt.max_score', '5.00');
    }

    public function test_incorrect_answers_score_zero_points(): void
    {
        [$singleChoice] = $this->seedQuestions();

        $student = $this->student();
        $service = $this->createService();
        $service->curricula()->sync([$this->curriculum->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $attemptId = $this->postJson("/api/quizzes/{$this->quiz->id}/attempts")->json('attempt.id');

        $submit = $this->postJson("/api/quizzes/attempts/{$attemptId}/submit", [
            'answers' => [
                ['question_id' => $singleChoice->id, 'answer' => '3'],
            ],
        ]);

        $submit->assertOk();
        $submit->assertJsonPath('attempt.answers.0.is_correct', false);
        $submit->assertJsonPath('attempt.answers.0.points_awarded', '0.00');
    }

    public function test_attempt_numbers_increment_across_multiple_attempts(): void
    {
        $this->seedQuestions();

        $student = $this->student();
        $service = $this->createService();
        $service->curricula()->sync([$this->curriculum->id]);
        $this->paidBookingFor($student, $service);

        Sanctum::actingAs($student);

        $first = $this->postJson("/api/quizzes/{$this->quiz->id}/attempts");
        $second = $this->postJson("/api/quizzes/{$this->quiz->id}/attempts");

        $first->assertJsonPath('attempt.attempt_number', 1);
        $second->assertJsonPath('attempt.attempt_number', 2);
    }
}
