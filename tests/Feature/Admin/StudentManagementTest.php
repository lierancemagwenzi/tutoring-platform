<?php

namespace Tests\Feature\Admin;

use App\Models\Enrollment;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_students(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['first_name' => 'Alice']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/students?search=Alice');

        $response->assertOk();
        $response->assertJsonPath('students.0.email', $student->email);
    }

    public function test_admin_can_view_student_detail_with_enrollments(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $course = $tutor->selfPacedCourses()->create(['title' => 'Algebra Basics', 'price' => 100, 'currency' => 'ZAR']);
        Enrollment::create([
            'student_id' => $student->id,
            'self_paced_course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/admin/students/{$student->id}");

        $response->assertOk();
        $response->assertJsonPath('enrollments.0.course_title', 'Algebra Basics');
        $response->assertJsonPath('enrollments.0.certificate_issued', false);
    }

    public function test_admin_can_view_student_detail_with_guardian_info(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['phone' => '0821234567', 'date_of_birth' => '2012-01-01']);
        $student->studentGuardian()->create([
            'guardian_first_name' => 'Jane', 'guardian_last_name' => 'Doe',
            'guardian_email' => 'jane@example.com', 'guardian_phone' => '0839876543',
            'relationship_to_student' => 'Mother',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/admin/students/{$student->id}");

        $response->assertOk();
        $response->assertJsonPath('student.phone', '0821234567');
        $response->assertJsonPath('student.date_of_birth', '2012-01-01');
        $response->assertJsonPath('guardian.name', 'Jane Doe');
        $response->assertJsonPath('guardian.relationship_to_student', 'Mother');
    }

    public function test_admin_cannot_view_a_tutor_via_student_detail_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/admin/students/{$tutorUser->id}");

        $response->assertNotFound();
    }
}
