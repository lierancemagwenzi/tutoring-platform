<?php

namespace Tests\Feature\Admin;

use App\Models\CourseCertificate;
use App\Models\Enrollment;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateManagementTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(): CourseCertificate
    {
        $student = User::factory()->create();
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $course = $tutor->selfPacedCourses()->create(['title' => 'Algebra Basics', 'price' => 100, 'currency' => 'ZAR']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'self_paced_course_id' => $course->id,
            'status' => 'completed',
            'enrolled_at' => now(),
            'completed_at' => now(),
        ]);

        return CourseCertificate::create([
            'enrollment_id' => $enrollment->id,
            'certificate_number' => 'CERT-2026-TEST01',
            'verification_uuid' => (string) Str::uuid(),
            'student_name' => trim("{$student->first_name} {$student->last_name}"),
            'course_title' => $course->title,
            'tutor_name' => $tutor->display_name,
            'issued_at' => now(),
            'pdf_path' => 'certificates/CERT-2026-TEST01.pdf',
        ]);
    }

    public function test_admin_can_list_certificates(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = $this->certificate();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/certificates');

        $response->assertOk();
        $response->assertJsonPath('certificates.0.certificate_number', $certificate->certificate_number);
    }

    public function test_admin_can_verify_a_certificate_by_number(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = $this->certificate();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/certificates/verify?certificate_number={$certificate->certificate_number}");

        $response->assertOk();
        $response->assertJsonPath('valid', true);
    }

    public function test_verifying_an_unknown_certificate_number_returns_invalid(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/certificates/verify?certificate_number=DOES-NOT-EXIST');

        $response->assertOk();
        $response->assertJsonPath('valid', false);
    }
}
