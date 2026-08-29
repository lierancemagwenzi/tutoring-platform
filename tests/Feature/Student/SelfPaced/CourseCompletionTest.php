<?php

namespace Tests\Feature\Student\SelfPaced;

use App\Models\CourseCertificate;
use App\Models\Curriculum;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    /**
     * A two-module course: module 1 needs one required activity completed,
     * module 2 needs one required (SurveyJS) assessment passed. Completing
     * both, in order, should unlock module 2 then complete the course.
     */
    public function test_completing_all_required_content_unlocks_the_next_module_completes_the_course_and_issues_a_certificate(): void
    {
        $tutor = $this->tutor();

        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Full Course', 'price' => 100, 'currency' => 'ZAR',
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
            'grade_id' => Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true])->id,
            'subject_id' => Subject::create(['name' => 'Mathematics', 'is_active' => true])->id,
            'curriculum_id' => Curriculum::create(['name' => 'CAPS', 'is_active' => true])->id,
        ]);
        $surveyQuestion = $survey->questions()->create([
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

        $student = User::factory()->create();
        Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($student);

        // Module 2's content is locked until module 1 completes.
        $blocked = $this->getJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}");
        $blocked->assertUnprocessable();

        $this->patchJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}/complete")->assertOk();

        $afterModule1 = $this->getJson("/api/student/self-paced-courses/{$course->id}");
        $afterModule1->assertJsonPath('course.modules.0.state', 'completed');
        $afterModule1->assertJsonPath('course.modules.1.state', 'current');

        $start = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts");
        $start->assertCreated();
        $attemptId = $start->json('attempt.id');

        $complete = $this->postJson(
            "/api/student/self-paced-courses/{$course->id}/assessments/{$assessment->id}/attempts/{$attemptId}/complete",
            ['raw_result' => ["question_{$surveyQuestion->id}" => '4']],
        );
        $complete->assertOk();
        $complete->assertJsonPath('attempt.passed', true);

        $enrollment = Enrollment::where('student_id', $student->id)->where('self_paced_course_id', $course->id)->first();
        $this->assertSame('completed', $enrollment->fresh()->status->value);
        $this->assertNotNull($enrollment->fresh()->completed_at);

        $certificate = CourseCertificate::where('enrollment_id', $enrollment->id)->first();
        $this->assertNotNull($certificate);
        $this->assertSame('Full Course', $certificate->course_title);
        $this->assertNotNull($certificate->pdf_path);

        $finalBootstrap = $this->getJson("/api/student/self-paced-courses/{$course->id}");
        $finalBootstrap->assertJsonPath('course.modules.1.state', 'completed');
        $finalBootstrap->assertJsonPath('course.progress.overall_percentage', 100);
        $finalBootstrap->assertJsonPath('course.enrollment.status', 'completed');
    }
}
