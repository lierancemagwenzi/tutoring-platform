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

class CourseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    /**
     * A two-module course (one required rich_text activity, one required
     * SurveyJS assessment passable with answer '4') plus a helper to enroll
     * and progress a student through it by a given number of steps:
     * 0 = just enrolled, 1 = activity done, 2 = assessment passed (course complete).
     *
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

    private function enrollAndProgress(SelfPacedCourse $course, object $refs, int $steps): User
    {
        $student = User::factory()->create();
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

    public function test_course_overview_reflects_students_at_different_stages(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithTwoModules($tutor);

        $this->enrollAndProgress($course, $refs, 0); // not started
        $this->enrollAndProgress($course, $refs, 1); // in progress
        $this->enrollAndProgress($course, $refs, 2); // completed

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/analytics");

        $response->assertOk();
        $response->assertJsonPath('analytics.total_enrollments', 3);
        $response->assertJsonPath('analytics.active_students', 2);
        $response->assertJsonPath('analytics.completed_students', 1);
        $response->assertJsonPath('analytics.completion_rate', 33.33);
        $response->assertJsonPath('analytics.certificates_issued', 1);
        // Not-started (0%) + in-progress (50%, module 1 of 2) + completed (100%) averaged.
        $response->assertJsonPath('analytics.average_course_progress', 50);
        $response->assertJsonPath('analytics.average_assessment_score', 100);
    }

    public function test_a_course_with_no_enrollments_reports_zeroed_stats_without_error(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course] = $this->courseWithTwoModules($tutor);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/analytics");

        $response->assertOk();
        $response->assertJsonPath('analytics.total_enrollments', 0);
        $response->assertJsonPath('analytics.completion_rate', 0);
        $response->assertJsonPath('analytics.average_course_progress', 0);
        $response->assertJsonPath('analytics.average_assessment_score', null);
    }

    public function test_tutor_cannot_view_analytics_for_another_tutors_course(): void
    {
        $owner = $this->tutor();
        [$course] = $this->courseWithTwoModules($owner);

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $intruder->id, 'display_name' => 'Intruder']);
        Sanctum::actingAs($intruder);

        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/analytics");

        $response->assertForbidden();
    }
}
