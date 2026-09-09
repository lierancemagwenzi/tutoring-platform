<?php

namespace Tests\Feature\Tutor;

use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuizBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Curriculum $curriculum;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
    }

    private function tutorWithQuiz(): array
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $this->subject->id,
            'status' => 'approved',
        ]);
        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $this->grade->id]);

        $course = $tutorProfile->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'A complete introduction to algebraic concepts.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'draft',
        ]);

        $chapter = $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'draft']);
        $lesson = $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'draft']);
        $quiz = $lesson->quizzes()->create(['title' => 'Chapter Check-in', 'status' => 'draft', 'settings' => []]);

        return [$user->fresh(), $quiz];
    }

    public function test_tutor_can_update_a_quiz(): void
    {
        [$tutor, $quiz] = $this->tutorWithQuiz();
        Sanctum::actingAs($tutor);

        $response = $this->putJson("/api/tutor/quizzes/{$quiz->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated description.',
        ]);

        $response->assertOk()->assertJsonPath('quiz.title', 'Updated Title');
    }

    public function test_tutor_can_add_a_single_choice_question(): void
    {
        [$tutor, $quiz] = $this->tutorWithQuiz();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/quizzes/{$quiz->id}/questions", [
            'type' => 'radiogroup',
            'text' => 'What is 2 + 2?',
            'choices' => ['3', '4', '5'],
            'correct_answer' => '4',
            'points' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('question.type', 'radiogroup');
        $response->assertJsonPath('question.correct_answer', '4');
    }

    public function test_single_choice_question_requires_at_least_two_choices(): void
    {
        [$tutor, $quiz] = $this->tutorWithQuiz();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/quizzes/{$quiz->id}/questions", [
            'type' => 'radiogroup',
            'text' => 'What is 2 + 2?',
            'choices' => ['4'],
            'correct_answer' => '4',
            'points' => 1,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('choices');
    }

    public function test_short_text_question_does_not_require_a_correct_answer(): void
    {
        [$tutor, $quiz] = $this->tutorWithQuiz();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/quizzes/{$quiz->id}/questions", [
            'type' => 'text',
            'text' => 'Explain your reasoning.',
            'points' => 1,
        ]);

        $response->assertCreated();
    }

    public function test_tutor_can_reorder_questions(): void
    {
        [$tutor, $quiz] = $this->tutorWithQuiz();
        $first = $quiz->questions()->create(['position' => 0, 'type' => 'text', 'definition' => ['title' => 'First'], 'points' => 1]);
        $second = $quiz->questions()->create(['position' => 1, 'type' => 'text', 'definition' => ['title' => 'Second'], 'points' => 1]);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/quizzes/{$quiz->id}/questions/reorder", [
            'question_ids' => [$second->id, $first->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_tutor_can_delete_a_question(): void
    {
        [$tutor, $quiz] = $this->tutorWithQuiz();
        $question = $quiz->questions()->create(['position' => 0, 'type' => 'text', 'definition' => ['title' => 'First'], 'points' => 1]);

        Sanctum::actingAs($tutor);

        $response = $this->deleteJson("/api/tutor/quiz-questions/{$question->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('quiz_questions', ['id' => $question->id]);
    }

    public function test_publishing_a_quiz_requires_at_least_one_question(): void
    {
        [$tutor, $quiz] = $this->tutorWithQuiz();
        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/quizzes/{$quiz->id}/publish");

        $response->assertUnprocessable()->assertJsonValidationErrors('questions');
    }

    public function test_tutor_can_publish_a_quiz_with_questions(): void
    {
        [$tutor, $quiz] = $this->tutorWithQuiz();
        $quiz->questions()->create(['position' => 0, 'type' => 'text', 'definition' => ['title' => 'First'], 'points' => 1]);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/quizzes/{$quiz->id}/publish");

        $response->assertOk()->assertJsonPath('quiz.status', 'published');
    }

    public function test_tutor_cannot_manage_another_tutors_quiz(): void
    {
        [$owner, $quiz] = $this->tutorWithQuiz();
        [$other] = $this->tutorWithQuiz();

        Sanctum::actingAs($other);

        $response = $this->putJson("/api/tutor/quizzes/{$quiz->id}", [
            'title' => 'Hijacked',
        ]);

        $response->assertForbidden();
    }
}
