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

class StudentProgressTest extends TestCase
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

    public function test_student_detail_shows_full_journey_after_course_completion(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course, $refs] = $this->courseWithTwoModules($tutor);

        $student = User::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($student);
        $this->patchJson("/api/student/self-paced-courses/{$course->id}/activities/{$refs->activityId}/complete")->assertOk();
        $start = $this->postJson("/api/student/self-paced-courses/{$course->id}/assessments/{$refs->assessmentId}/attempts");
        $attemptId = $start->json('attempt.id');
        $this->postJson(
            "/api/student/self-paced-courses/{$course->id}/assessments/{$refs->assessmentId}/attempts/{$attemptId}/complete",
            ['raw_result' => ["question_{$refs->questionId}" => '4']],
        )->assertOk();

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments/{$enrollment->id}");

        $response->assertOk();
        $response->assertJsonPath('student_progress.student.email', $student->email);
        $response->assertJsonPath('student_progress.enrollment.status', 'completed');
        $response->assertJsonPath('student_progress.progress.overall_percentage', 100);
        $response->assertJsonPath('student_progress.certificate.certificate_number', fn ($v) => $v !== null);

        $chapters = $response->json('student_progress.chapters');
        $this->assertCount(2, $chapters);
        $this->assertSame('completed', $chapters[0]['state']);
        $this->assertSame('completed', $chapters[1]['state']);
        $this->assertEquals(100, $chapters[0]['completion_percentage']);

        $module1Blocks = $chapters[0]['lesson_blocks'];
        $this->assertSame('activity', $module1Blocks[0]['kind']);
        $this->assertTrue($module1Blocks[0]['completed']);

        $module2Blocks = $chapters[1]['lesson_blocks'];
        $this->assertSame('assessment', $module2Blocks[0]['kind']);
        $this->assertTrue($module2Blocks[0]['passed']);
        $this->assertEquals(100, $module2Blocks[0]['best_score']);
        $this->assertSame(1, $module2Blocks[0]['attempts']);

        // completed_at columns are whole-second precision, so events
        // triggered within the same request (module completion right after
        // the assessment that caused it) can legitimately tie down to the
        // second — only the bookends and the overall multiset of events are
        // guaranteed, not a strict order through the tied middle.
        $timeline = $response->json('student_progress.timeline');
        $types = array_column($timeline, 'type');
        $this->assertSame('enrolled', $types[0]);
        $this->assertSame('certificate_earned', end($types));
        $this->assertEqualsCanonicalizing(
            ['enrolled', 'chapter_completed', 'chapter_completed', 'assessment_passed', 'certificate_earned'],
            $types,
        );
    }

    public function test_student_detail_for_a_barely_started_enrollment(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$course] = $this->courseWithTwoModules($tutor);

        $student = User::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments/{$enrollment->id}");

        $response->assertOk();
        $response->assertJsonPath('student_progress.progress.overall_percentage', 0);
        $response->assertJsonPath('student_progress.certificate', null);
        $response->assertJsonPath('student_progress.chapters.0.state', 'current');
        $response->assertJsonPath('student_progress.chapters.1.state', 'locked');
        $response->assertJsonPath('student_progress.timeline.0.type', 'enrolled');
        $this->assertCount(1, $response->json('student_progress.timeline'));
    }

    public function test_tutor_cannot_view_a_student_belonging_to_another_tutors_course(): void
    {
        $ownerUser = User::factory()->tutor()->create();
        $owner = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $ownerUser->id, 'display_name' => 'Owner']);
        [$course] = $this->courseWithTwoModules($owner);

        $student = User::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $intruder->id, 'display_name' => 'Intruder']);
        Sanctum::actingAs($intruder);

        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/enrollments/{$enrollment->id}");

        $response->assertForbidden();
    }

    public function test_enrollment_from_a_different_course_returns_404(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        [$courseA] = $this->courseWithTwoModules($tutor);
        [$courseB] = $this->courseWithTwoModules($tutor);

        $student = User::factory()->create();
        $enrollmentInB = Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $courseB->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$courseA->id}/enrollments/{$enrollmentInB->id}");

        $response->assertNotFound();
    }
}
