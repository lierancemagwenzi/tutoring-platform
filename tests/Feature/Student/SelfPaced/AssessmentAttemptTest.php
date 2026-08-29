<?php

namespace Tests\Feature\Student\SelfPaced;

use App\Models\Curriculum;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SelfPacedCourse;
use App\Models\SelfPacedSurveyQuestion;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithH5pLibrary;
use Tests\TestCase;

class AssessmentAttemptTest extends TestCase
{
    use InteractsWithH5pLibrary, RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function courseWithSurveyAssessment(TutorProfile $tutor, array $overrides = []): array
    {
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Test Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        $survey = $tutor->selfPacedSurveyContents()->create([
            'title' => 'Quiz Bank',
            'grade_id' => Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true])->id,
            'subject_id' => Subject::create(['name' => 'Mathematics', 'is_active' => true])->id,
            'curriculum_id' => Curriculum::create(['name' => 'CAPS', 'is_active' => true])->id,
        ]);
        $survey->questions()->create([
            'position' => 0, 'type' => 'radiogroup',
            'definition' => ['title' => 'What is 2+2?', 'choices' => ['3', '4', '5'], 'correctAnswer' => '4'],
            'points' => 10,
        ]);

        $module = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => false, 'assessment_completion_required' => true,
        ]);

        $assessment = $module->assessments()->create(array_merge([
            'assessment_type' => 'chapter_test',
            'title' => 'Chapter 1 Quiz',
            'position' => 0,
            'required' => true,
            'passing_score' => 70,
            'attempts_mode' => 'unlimited',
            'provider' => 'surveyjs',
            'provider_config' => ['survey_content_id' => $survey->id],
        ], $overrides));

        return [$course, $module, $assessment];
    }

    private function enroll(User $student, SelfPacedCourse $course): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);
    }

    public function test_student_can_view_survey_content_without_correct_answers(): void
    {
        [$course, , $assessment] = $this->courseWithSurveyAssessment($this->tutor());
        $student = User::factory()->create();
        $this->enroll($student, $course);

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}");

        $response->assertOk();
        $response->assertJsonPath('assessment.survey_json.elements.0.title', 'What is 2+2?');
        $response->assertJsonMissingPath('assessment.survey_json.elements.0.correctAnswer');
    }

    public function test_student_can_start_and_pass_a_surveyjs_assessment(): void
    {
        [$course, , $assessment] = $this->courseWithSurveyAssessment($this->tutor());
        $student = User::factory()->create();
        $this->enroll($student, $course);

        Sanctum::actingAs($student);

        $start = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts");
        $start->assertCreated();
        $attemptId = $start->json('attempt.id');
        $surveyQuestionId = SelfPacedSurveyQuestion::first()->id;

        $complete = $this->postJson(
            "/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts/{$attemptId}/complete",
            ['raw_result' => ["question_{$surveyQuestionId}" => '4']],
        );

        $complete->assertOk();
        $complete->assertJsonPath('attempt.passed', true);
        $complete->assertJsonPath('attempt.percentage', '100.00');
    }

    public function test_a_failing_score_does_not_pass_the_assessment(): void
    {
        [$course, , $assessment] = $this->courseWithSurveyAssessment($this->tutor());
        $student = User::factory()->create();
        $this->enroll($student, $course);
        $surveyQuestionId = SelfPacedSurveyQuestion::first()->id;

        Sanctum::actingAs($student);
        $start = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts");
        $attemptId = $start->json('attempt.id');

        $complete = $this->postJson(
            "/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts/{$attemptId}/complete",
            ['raw_result' => ["question_{$surveyQuestionId}" => '3']],
        );

        $complete->assertOk();
        $complete->assertJsonPath('attempt.passed', false);
        $complete->assertJsonPath('attempt.percentage', '0.00');
    }

    public function test_starting_an_attempt_resumes_the_open_one_instead_of_creating_a_new_one(): void
    {
        [$course, , $assessment] = $this->courseWithSurveyAssessment($this->tutor());
        $student = User::factory()->create();
        $this->enroll($student, $course);

        Sanctum::actingAs($student);
        $first = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts");
        $second = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts");

        $this->assertSame($first->json('attempt.id'), $second->json('attempt.id'));
    }

    public function test_max_attempts_is_enforced_when_limited(): void
    {
        [$course, , $assessment] = $this->courseWithSurveyAssessment($this->tutor(), [
            'attempts_mode' => 'limited',
            'max_attempts' => 1,
        ]);
        $student = User::factory()->create();
        $this->enroll($student, $course);
        $surveyQuestionId = SelfPacedSurveyQuestion::first()->id;

        Sanctum::actingAs($student);
        $start = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts");
        $attemptId = $start->json('attempt.id');
        $this->postJson(
            "/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts/{$attemptId}/complete",
            ['raw_result' => ["question_{$surveyQuestionId}" => '3']], // fails
        )->assertOk();

        $secondStart = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts");

        $secondStart->assertUnprocessable();
    }

    public function test_h5p_assessment_is_scored_from_xapi_statement(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'H5P Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);
        $module = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => false, 'assessment_completion_required' => true,
        ]);
        $assessment = $module->assessments()->create([
            'assessment_type' => 'chapter_test',
            'title' => 'H5P Quiz',
            'position' => 0,
            'required' => true,
            'passing_score' => 50,
            'attempts_mode' => 'unlimited',
            'provider' => 'h5p',
            'provider_config' => ['h5p_content_id' => 'abc123'],
        ]);
        $student = User::factory()->create();
        $this->enroll($student, $course);

        Sanctum::actingAs($student);
        $start = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts");
        $attemptId = $start->json('attempt.id');

        $complete = $this->postJson(
            "/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts/{$attemptId}/complete",
            ['raw_result' => [
                'statement' => [
                    'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
                    'object' => ['id' => 'abc123'],
                    'result' => ['score' => ['raw' => 8, 'max' => 10], 'completion' => true, 'success' => true],
                ],
            ]],
        );

        $complete->assertOk();
        $complete->assertJsonPath('attempt.percentage', '80.00');
        $complete->assertJsonPath('attempt.passed', true);
    }

    public function test_student_can_fetch_the_h5p_player_model_for_an_h5p_assessment(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'H5P Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);
        $module = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => false, 'assessment_completion_required' => true,
        ]);
        $assessment = $module->assessments()->create([
            'assessment_type' => 'chapter_test', 'title' => 'H5P Quiz', 'position' => 0, 'required' => true,
            'passing_score' => 50, 'attempts_mode' => 'unlimited',
            'provider' => 'h5p', 'provider_config' => ['h5p_content_id' => '123'],
        ]);
        $student = User::factory()->create();
        $this->enroll($student, $course);
        $this->seedH5pContent(123);

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/h5p-player-model");

        $response->assertOk();
        $response->assertJsonPath('integration.contents.cid-123.library', 'H5P.MultiChoice 1.16');
    }

    public function test_h5p_player_model_is_not_available_for_a_surveyjs_assessment(): void
    {
        [$course, , $assessment] = $this->courseWithSurveyAssessment($this->tutor());
        $student = User::factory()->create();
        $this->enroll($student, $course);

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/h5p-player-model");

        $response->assertNotFound();
    }

    public function test_non_enrolled_student_cannot_fetch_the_h5p_player_model(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'H5P Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);
        $module = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => false, 'assessment_completion_required' => true,
        ]);
        $assessment = $module->assessments()->create([
            'assessment_type' => 'chapter_test', 'title' => 'H5P Quiz', 'position' => 0, 'required' => true,
            'passing_score' => 50, 'attempts_mode' => 'unlimited',
            'provider' => 'h5p', 'provider_config' => ['h5p_content_id' => '123'],
        ]);
        $student = User::factory()->create();

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/h5p-player-model");

        $response->assertForbidden();
    }
}
