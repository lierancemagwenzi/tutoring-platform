<?php

namespace Tests\Feature\Student\SelfPaced;

use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function courseWithTwoModules(TutorProfile $tutor): SelfPacedCourse
    {
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Test Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        $module1 = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
        $module1->activities()->create([
            'type' => 'rich_text', 'title' => 'Intro', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>Hi</p>'],
        ]);

        $module2 = $course->modules()->create([
            'title' => 'Module 2', 'position' => 1,
            'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
        $module2->activities()->create([
            'type' => 'rich_text', 'title' => 'Next', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>Next</p>'],
        ]);

        return $course;
    }

    public function test_bootstrap_shows_first_module_current_and_second_locked(): void
    {
        $tutor = $this->tutor();
        $course = $this->courseWithTwoModules($tutor);
        $student = User::factory()->create();

        Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}");

        $response->assertOk();
        $response->assertJsonPath('course.modules.0.state', 'current');
        $response->assertJsonPath('course.modules.1.state', 'locked');
        $response->assertJsonPath('course.progress.total_chapters', 2);
        $response->assertJsonPath('course.progress.completed_chapters', 0);
        $response->assertJsonPath('course.progress.overall_percentage', 0);
    }

    public function test_non_enrolled_student_is_forbidden(): void
    {
        $tutor = $this->tutor();
        $course = $this->courseWithTwoModules($tutor);
        $student = User::factory()->create();

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}");

        $response->assertForbidden();
    }
}
