<?php

namespace Tests\Feature\Student\SelfPaced;

use App\Models\CourseCertificate;
use App\Models\Enrollment;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\LearnerProgress\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private function completedEnrollment(): Enrollment
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Completed Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        $student = User::factory()->create();

        return Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'completed', 'enrolled_at' => now(), 'completed_at' => now(),
        ]);
    }

    public function test_generating_a_certificate_twice_returns_the_same_row(): void
    {
        $enrollment = $this->completedEnrollment();
        $service = app(CertificateService::class);

        $first = $service->generate($enrollment);
        $second = $service->generate($enrollment->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CourseCertificate::where('enrollment_id', $enrollment->id)->count());
    }

    public function test_the_owning_student_can_list_and_download_their_certificate(): void
    {
        $enrollment = $this->completedEnrollment();
        $certificate = app(CertificateService::class)->generate($enrollment);
        $student = $enrollment->student;

        Sanctum::actingAs($student);

        $list = $this->getJson('/api/student/certificates');
        $list->assertOk();
        $list->assertJsonPath('certificates.0.certificate_number', $certificate->certificate_number);
        $list->assertJsonPath('certificates.0.self_paced_course_id', $enrollment->self_paced_course_id);

        $download = $this->get("/api/student/certificates/{$certificate->id}/download");
        $download->assertOk();
        $download->assertHeader('content-type', 'application/pdf');
    }

    public function test_another_student_cannot_download_someone_elses_certificate(): void
    {
        $enrollment = $this->completedEnrollment();
        $certificate = app(CertificateService::class)->generate($enrollment);
        $intruder = User::factory()->create();

        Sanctum::actingAs($intruder);

        $list = $this->getJson('/api/student/certificates');
        $list->assertOk();
        $list->assertJsonCount(0, 'certificates');

        $download = $this->get("/api/student/certificates/{$certificate->id}/download");
        $download->assertForbidden();
    }
}
