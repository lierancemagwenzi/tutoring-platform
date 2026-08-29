<?php

namespace Tests\Feature\Tutor\SelfPaced\Analytics;

use App\Models\Curriculum;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SelfPacedCourse;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAssessmentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A single-module course with one unlimited-attempt SurveyJS assessment,
     * so a student can fail then pass to build up a real attempt history.
     *
     * @return array{0: SelfPacedCourse, 1: object{assessmentId: int, questionId: int}}
     */
    private function courseWithAssessment(TutorProfile $tutor): array
    {
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Assessment Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        $survey = $tutor->selfPacedSurveyContents()->create([
            'title' => 'Quiz Bank',
            'grade_id' => Grade::firstOrCreate(['level' => 10], ['name' => 'Grade 10', 'is_active' => true])->id,
            'subject_id' => Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true])->id,
            'curriculum_id' => Curriculum::firstOrCreate(['name' => 'CAPS'], ['is_active' => true])->id,
        ]);
        $question = $survey->questions()->create([
            'position' => 0, 'type' => 'radiogroup',
            'definition' => ['title' => 'What is 2+2?', 'choices' => ['3', '4', '5'], 'correctAnswer' => '4'],
            'points' => 10,
        ]);

        $module = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => false, 'assessment_completion_required' => true,
        ]);
        $assessment = $module->assessments()->create([
            'assessment_type' => 'chapter_test', 'title' => 'Quiz', 'position' => 0, 'required' => true,
            'passing_score' => 70, 'attempts_mode' => 'unlimited',
            'provider' => 'surveyjs', 'provider_config' => ['survey_content_id' => $survey->id],
        ]);

        return [$course, (object) ['assessmentId' => $assessment->id, 'questionId' => $question->id]];
    }

    private function attempt(SelfPacedCourse $course, object $refs, string $answer): array
    {
        $start = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$refs->assessmentId}/attempts");
        $attemptId = $start->json('attempt.id');
        $this->postJson(
            "/api/student/self-paced-courses/{$course->id}/assessments/{$refs->assessmentId}/attempts/{$attemptId}/complete",
            ['raw_result' => ["question_{$refs->questionId}" => $answer]],
        )->assertOk();

        return $start->json();
    }

    public function test_shows_every_attempt_with_best_and_latest_scores(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithAssessment($tutor);

        $student = User::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($student);
        $this->attempt($course, $refs, '3'); // fails, 0%
        $this->attempt($course, $refs, '4'); // passes, 100%

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments/{$enrollment->id}/assessments");

        $response->assertOk();
        $assessments = $response->json('assessments');
        $this->assertCount(1, $assessments);

        $group = $assessments[0];
        $this->assertSame('Quiz', $group['assessment_title']);
        $this->assertTrue($group['passed']);
        $this->assertEquals(100, $group['best_score']);
        $this->assertEquals(100, $group['latest_score']);
        $this->assertCount(2, $group['attempts']);
        $this->assertSame(1, $group['attempts'][0]['attempt_number']);
        $this->assertEquals(0, $group['attempts'][0]['percentage']);
        $this->assertFalse($group['attempts'][0]['passed']);
        $this->assertSame(2, $group['attempts'][1]['attempt_number']);
        $this->assertTrue($group['attempts'][1]['passed']);
    }

    public function test_an_assessment_never_attempted_still_appears_with_null_scores(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course] = $this->courseWithAssessment($tutor);

        $student = User::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments/{$enrollment->id}/assessments");

        $response->assertOk();
        $response->assertJsonCount(1, 'assessments');
        $response->assertJsonPath('assessments.0.best_score', null);
        $response->assertJsonPath('assessments.0.passed', false);
        $response->assertJsonCount(0, 'assessments.0.attempts');
    }

    public function test_tutor_cannot_view_assessments_for_another_tutors_course(): void
    {
        $ownerUser = User::factory()->tutor()->create();
        $owner = TutorProfile::create(['user_id' => $ownerUser->id, 'display_name' => 'Owner']);
        [$course] = $this->courseWithAssessment($owner);

        $student = User::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $intruder->id, 'display_name' => 'Intruder']);
        Sanctum::actingAs($intruder);

        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments/{$enrollment->id}/assessments");

        $response->assertForbidden();
    }
}
