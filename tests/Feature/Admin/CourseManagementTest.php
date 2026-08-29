<?php

namespace Tests\Feature\Admin;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    public function test_admin_can_list_courses_filtered_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = $this->tutor();
        $tutor->selfPacedCourses()->create(['title' => 'Draft Course', 'status' => 'draft']);
        $tutor->selfPacedCourses()->create(['title' => 'Published Course', 'status' => 'published']);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/self-paced-courses?status=published');

        $response->assertOk();
        $response->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.title', 'Published Course');
    }

    public function test_admin_can_inspect_course_module_tree_without_modifying_progress(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create(['title' => 'Ready Course', 'price' => 50, 'currency' => 'ZAR']);
        $module = $course->modules()->create(['title' => 'Module 1', 'position' => 0]);
        $module->activities()->create([
            'type' => 'rich_text', 'title' => 'Intro', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>Hi</p>'],
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/admin/self-paced-courses/{$course->id}");

        $response->assertOk();
        $response->assertJsonPath('course.modules.0.title', 'Module 1');
        $response->assertJsonPath('course.modules.0.activities.0.title', 'Intro');
        $response->assertJsonPath('course.enrollments_count', 0);
    }
}
