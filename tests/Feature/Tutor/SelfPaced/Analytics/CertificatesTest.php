<?php

namespace Tests\Feature\Tutor\SelfPaced\Analytics;

use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\LearnerProgress\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificatesTest extends TestCase
{
    use RefreshDatabase;

    private function completedEnrollment(TutorProfile $tutor, ?SelfPacedCourse $course = null): Enrollment
    {
        $course ??= $tutor->selfPacedCourses()->create([
            'title' => 'Completed Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        $student = User::factory()->create();

        return Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'completed', 'enrolled_at' => now(), 'completed_at' => now(),
        ]);
    }

    public function test_lists_every_certificate_issued_for_the_course(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Completed Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        $enrollmentA = $this->completedEnrollment($tutor, $course);
        $enrollmentB = $this->completedEnrollment($tutor, $course);
        $certificateA = app(CertificateService::class)->generate($enrollmentA);
        $certificateB = app(CertificateService::class)->generate($enrollmentB);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/certificates");

        $response->assertOk();
        $response->assertJsonCount(2, 'certificates');
        $response->assertJsonPath('meta.total', 2);

        $numbers = collect($response->json('certificates'))->pluck('certificate_number');
        $this->assertTrue($numbers->contains($certificateA->certificate_number));
        $this->assertTrue($numbers->contains($certificateB->certificate_number));
    }

    public function test_a_course_with_no_certificates_returns_an_empty_list(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Fresh Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/certificates");

        $response->assertOk();
        $response->assertJsonCount(0, 'certificates');
    }

    public function test_tutor_can_download_a_certificate_issued_for_their_course(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $enrollment = $this->completedEnrollment($tutor);
        $certificate = app(CertificateService::class)->generate($enrollment);

        Sanctum::actingAs($tutorUser);
        $response = $this->get("/api/tutor/self-paced-courses/{$enrollment->self_paced_course_id}/certificates/{$certificate->id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_tutor_cannot_download_a_certificate_from_another_tutors_course(): void
    {
        $ownerUser = User::factory()->tutor()->create();
        $owner = TutorProfile::create(['user_id' => $ownerUser->id, 'display_name' => 'Owner']);
        $enrollment = $this->completedEnrollment($owner);
        $certificate = app(CertificateService::class)->generate($enrollment);

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $intruder->id, 'display_name' => 'Intruder']);
        Sanctum::actingAs($intruder);

        $response = $this->get("/api/tutor/self-paced-courses/{$enrollment->self_paced_course_id}/certificates/{$certificate->id}/download");

        $response->assertForbidden();
    }

    public function test_a_certificate_belonging_to_a_different_course_returns_404(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $courseA = $tutor->selfPacedCourses()->create([
            'title' => 'Course A', 'price' => 100, 'currency' => 'ZAR', 'status' => 'published', 'visibility' => 'public',
        ]);
        $enrollmentInB = $this->completedEnrollment($tutor);
        $certificateInB = app(CertificateService::class)->generate($enrollmentInB);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson("/api/tutor/self-paced-courses/{$courseA->id}/certificates/{$certificateInB->id}/download");

        $response->assertNotFound();
    }

    public function test_tutor_cannot_list_certificates_for_another_tutors_course(): void
    {
        $ownerUser = User::factory()->tutor()->create();
        $owner = TutorProfile::create(['user_id' => $ownerUser->id, 'display_name' => 'Owner']);
        $course = $owner->selfPacedCourses()->create([
            'title' => 'Course', 'price' => 100, 'currency' => 'ZAR', 'status' => 'published', 'visibility' => 'public',
        ]);

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $intruder->id, 'display_name' => 'Intruder']);
        Sanctum::actingAs($intruder);

        $response = $this->getJson("/api/tutor/self-paced-courses/{$course->id}/certificates");

        $response->assertForbidden();
    }
}
