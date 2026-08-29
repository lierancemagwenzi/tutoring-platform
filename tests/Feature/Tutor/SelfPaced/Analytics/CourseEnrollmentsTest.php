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

class CourseEnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: SelfPacedCourse, 1: object{activityId: int, assessmentId: int, questionId: int}}
     */
    private function courseWithTwoModules(TutorProfile $tutor): array
    {
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Analytics Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        $module1 = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
        $activity = $module1->activities()->create([
            'type' => 'rich_text', 'title' => 'Intro', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>Hi</p>'],
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

        $module2 = $course->modules()->create([
            'title' => 'Module 2', 'position' => 1,
            'activity_completion_required' => false, 'assessment_completion_required' => true,
        ]);
        $assessment = $module2->assessments()->create([
            'assessment_type' => 'chapter_test', 'title' => 'Final Quiz', 'position' => 0, 'required' => true,
            'passing_score' => 70, 'attempts_mode' => 'unlimited',
            'provider' => 'surveyjs', 'provider_config' => ['survey_content_id' => $survey->id],
        ]);

        return [$course, (object) ['activityId' => $activity->id, 'assessmentId' => $assessment->id, 'questionId' => $question->id]];
    }

    private function enrollAndProgress(SelfPacedCourse $course, object $refs, int $steps, ?string $firstName = null): User
    {
        $student = User::factory()->create($firstName ? ['first_name' => $firstName] : []);
        Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        if ($steps >= 1) {
            Sanctum::actingAs($student);
            $this->patchJson("/api/student/self-paced-courses/{$course->id}/activities/{$refs->activityId}/complete")->assertOk();
        }

        if ($steps >= 2) {
            $start = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$refs->assessmentId}/attempts");
            $attemptId = $start->json('attempt.id');
            $this->postJson(
                "/api/student/self-paced-courses/{$course->id}/assessments/{$refs->assessmentId}/attempts/{$attemptId}/complete",
                ['raw_result' => ["question_{$refs->questionId}" => '4']],
            )->assertOk();
        }

        return $student;
    }

    public function test_lists_every_enrolled_student_with_computed_columns(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithTwoModules($tutor);

        $notStarted = $this->enrollAndProgress($course, $refs, 0, 'Alice');
        $completed = $this->enrollAndProgress($course, $refs, 2, 'Carol');

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments");

        $response->assertOk();
        $response->assertJsonCount(2, 'enrollments');
        $response->assertJsonPath('meta.total', 2);

        $byEmail = collect($response->json('enrollments'))->keyBy('student.email');
        $this->assertSame('not_started', $byEmail[$notStarted->email]['course_status']);
        $this->assertEquals(0, $byEmail[$notStarted->email]['progress_percentage']);
        $this->assertSame('completed', $byEmail[$completed->email]['course_status']);
        $this->assertEquals(100, $byEmail[$completed->email]['progress_percentage']);
        $this->assertTrue($byEmail[$completed->email]['certificate_issued']);
        $this->assertNotNull($byEmail[$completed->email]['certificate_number']);

        // Never engaged: no last_accessed_at at all yet, so no meaningful
        // day count — and flagged inactive by definition.
        $this->assertNull($byEmail[$notStarted->email]['days_since_last_activity']);
        $this->assertTrue($byEmail[$notStarted->email]['is_inactive']);

        // Just engaged: a whole-number day count (not a long float), and not inactive.
        $this->assertIsInt($byEmail[$completed->email]['days_since_last_activity']);
        $this->assertSame(0, $byEmail[$completed->email]['days_since_last_activity']);
        $this->assertFalse($byEmail[$completed->email]['is_inactive']);
    }

    public function test_filters_by_status_completed(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithTwoModules($tutor);

        $this->enrollAndProgress($course, $refs, 0);
        $this->enrollAndProgress($course, $refs, 1);
        $completedStudent = $this->enrollAndProgress($course, $refs, 2);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments?status=completed");

        $response->assertOk();
        $response->assertJsonCount(1, 'enrollments');
        $response->assertJsonPath('enrollments.0.student.email', $completedStudent->email);
        $response->assertJsonPath('enrollments.0.certificate_issued', true);
    }

    public function test_filters_by_status_not_started_and_in_progress(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithTwoModules($tutor);

        $notStarted = $this->enrollAndProgress($course, $refs, 0);
        $inProgress = $this->enrollAndProgress($course, $refs, 1);
        $this->enrollAndProgress($course, $refs, 2);

        Sanctum::actingAs($tutorUser);

        $notStartedResponse = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments?status=not_started");
        $notStartedResponse->assertJsonCount(1, 'enrollments');
        $notStartedResponse->assertJsonPath('enrollments.0.student.email', $notStarted->email);

        $inProgressResponse = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments?status=in_progress");
        $inProgressResponse->assertJsonCount(1, 'enrollments');
        $inProgressResponse->assertJsonPath('enrollments.0.student.email', $inProgress->email);
    }

    public function test_filters_by_certificate_issued(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithTwoModules($tutor);

        $this->enrollAndProgress($course, $refs, 1);
        $certified = $this->enrollAndProgress($course, $refs, 2);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments?certificate_issued=1");

        $response->assertJsonCount(1, 'enrollments');
        $response->assertJsonPath('enrollments.0.student.email', $certified->email);
    }

    public function test_filters_by_student_name_search(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithTwoModules($tutor);

        $this->enrollAndProgress($course, $refs, 0, 'Zelda');
        $this->enrollAndProgress($course, $refs, 0, 'Yolanda');

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments?search=Zelda");

        $response->assertJsonCount(1, 'enrollments');
        $response->assertJsonPath('enrollments.0.student.name', fn ($name) => str_starts_with($name, 'Zelda'));
    }

    public function test_pagination_meta_is_correct_across_pages(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithTwoModules($tutor);

        for ($i = 0; $i < 5; $i++) {
            $this->enrollAndProgress($course, $refs, 0);
        }

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments?per_page=2");

        $response->assertJsonCount(2, 'enrollments');
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.last_page', 3);
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_tutor_cannot_list_enrollments_for_another_tutors_course(): void
    {
        $ownerUser = User::factory()->tutor()->create();
        $owner = TutorProfile::create(['user_id' => $ownerUser->id, 'display_name' => 'Owner']);
        [$course] = $this->courseWithTwoModules($owner);

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $intruder->id, 'display_name' => 'Intruder']);
        Sanctum::actingAs($intruder);

        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments");

        $response->assertForbidden();
    }
}
