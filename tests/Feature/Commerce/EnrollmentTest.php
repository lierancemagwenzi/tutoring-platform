<?php

namespace Tests\Feature\Commerce;

use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function course(TutorProfile $tutor, array $overrides = []): SelfPacedCourse
    {
        return $tutor->selfPacedCourses()->create(array_merge([
            'title' => 'Test Course',
            'price' => 100,
            'currency' => 'ZAR',
            'status' => 'published',
            'visibility' => 'public',
        ], $overrides));
    }

    public function test_student_only_sees_own_active_enrollments_in_my_courses_list(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $ownCourse = $this->course($tutor, ['title' => 'Own Course']);
        $otherCourse = $this->course($tutor, ['title' => 'Other Course']);
        $cancelledCourse = $this->course($tutor, ['title' => 'Cancelled Course']);

        Enrollment::create(['student_id' => $student->id, 'self_paced_course_id' => $ownCourse->id, 'status' => 'active', 'enrolled_at' => now()]);
        Enrollment::create(['student_id' => $otherStudent->id, 'self_paced_course_id' => $otherCourse->id, 'status' => 'active', 'enrolled_at' => now()]);
        Enrollment::create(['student_id' => $student->id, 'self_paced_course_id' => $cancelledCourse->id, 'status' => 'cancelled', 'enrolled_at' => now()]);

        $response = $this->getJson('/api/enrollments');

        $response->assertOk();
        $response->assertJsonCount(1, 'enrollments');
        $response->assertJsonPath('enrollments.0.course.title', 'Own Course');
    }

    public function test_access_endpoint_reports_true_for_owned_course_false_otherwise(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $owned = $this->course($tutor, ['title' => 'Owned']);
        $notOwned = $this->course($tutor, ['title' => 'Not Owned']);

        Enrollment::create(['student_id' => $student->id, 'self_paced_course_id' => $owned->id, 'status' => 'active', 'enrolled_at' => now()]);

        $this->getJson("/api/enrollments/access/{$owned->id}")->assertOk()->assertJsonPath('enrolled', true);
        $this->getJson("/api/enrollments/access/{$notOwned->id}")->assertOk()->assertJsonPath('enrolled', false);
    }

    public function test_cannot_view_another_students_enrollment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $tutor = $this->tutor();
        $course = $this->course($tutor);
        $enrollment = Enrollment::create([
            'student_id' => $owner->id,
            'self_paced_course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/enrollments/{$enrollment->id}")->assertForbidden();

        Sanctum::actingAs($owner);

        $this->getJson("/api/enrollments/{$enrollment->id}")->assertOk();
    }
}
