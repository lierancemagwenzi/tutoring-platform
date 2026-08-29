<?php

namespace Tests\Feature\Marketplace;

use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfPacedCourseOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function publishedCourse(TutorProfile $tutor, array $overrides = []): SelfPacedCourse
    {
        $course = $tutor->selfPacedCourses()->create(array_merge([
            'title' => 'Test Course',
            'price' => 100,
            'currency' => 'ZAR',
            'status' => 'published',
            'visibility' => 'public',
        ], $overrides));

        $module = $course->modules()->create(['title' => 'Module 1', 'position' => 0]);
        $module->activities()->create([
            'type' => 'rich_text', 'title' => 'Welcome', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>Hi</p>'],
        ]);

        return $course;
    }

    public function test_enrolled_student_sees_is_enrolled_true_on_card_and_details(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor);

        Enrollment::create([
            'student_id' => $student->id,
            'self_paced_course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->getJson('/api/marketplace/self-paced-courses')
            ->assertOk()
            ->assertJsonPath('courses.0.is_enrolled', true);

        $this->getJson("/api/marketplace/self-paced-courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('course.is_enrolled', true);
    }

    public function test_non_enrolled_student_sees_is_enrolled_false(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor);

        $this->getJson('/api/marketplace/self-paced-courses')
            ->assertOk()
            ->assertJsonPath('courses.0.is_enrolled', false);

        $this->getJson("/api/marketplace/self-paced-courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('course.is_enrolled', false);
    }
}
