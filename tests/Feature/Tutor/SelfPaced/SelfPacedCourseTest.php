<?php

namespace Tests\Feature\Tutor\SelfPaced;

use App\Enums\TutorSubjectStatus;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfPacedCourseTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): User
    {
        $tutorUser = User::factory()->tutor()->create();
        $profile = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        // Creating a self-paced course now requires banking details on file
        // (see BankingEligibilityService) — every test in this file needs one.
        $profile->bankAccount()->create([
            'bank_name' => 'Test Bank', 'account_holder_name' => 'Test Tutor',
            'account_number' => '123456789', 'branch_code' => '000000', 'account_type' => 'savings',
        ]);

        return $tutorUser;
    }

    /**
     * Publishing now requires an approved TutorSubject for the course's
     * subject (see MarketplaceEligibilityService) — this gives a tutor one.
     */
    private function approveSubjectFor(User $tutor): Subject
    {
        $subject = Subject::create(['name' => 'Mathematics']);
        TutorSubject::create([
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'subject_id' => $subject->id,
            'status' => TutorSubjectStatus::Approved,
        ]);

        return $subject;
    }

    public function test_tutor_can_create_a_self_paced_course(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/self-paced-courses', [
            'title' => 'Mastering Algebra',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('course.title', 'Mastering Algebra');
        $response->assertJsonPath('course.status', 'draft');
        $response->assertJsonPath('course.visibility', 'private');
        $this->assertDatabaseHas('self_paced_courses', [
            'title' => 'Mastering Algebra',
            'tutor_profile_id' => $tutor->tutorProfile->id,
        ]);
    }

    public function test_tutor_can_update_general_settings_and_upload_a_thumbnail(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->tutorProfile->selfPacedCourses()->create(['title' => 'Draft Course']);
        Sanctum::actingAs($tutor);

        $response = $this->putJson("/api/tutor/self-paced-courses/{$course->id}", [
            'subtitle' => 'A gentle introduction',
            'difficulty' => 'beginner',
            'language' => 'English',
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('course.subtitle', 'A gentle introduction');
        $response->assertJsonPath('course.difficulty', 'beginner');
        $this->assertNotNull($course->fresh()->thumbnail_path);
    }

    public function test_course_cannot_publish_without_modules_or_pricing(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->tutorProfile->selfPacedCourses()->create(['title' => 'Empty Course']);
        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/self-paced-courses/{$course->id}/publish");

        $response->assertStatus(422);
        $response->assertJsonPath('errors.course.0', 'The course must have at least one module.');
        $this->assertDatabaseHas('self_paced_courses', ['id' => $course->id, 'status' => 'draft']);
    }

    public function test_course_publishes_once_a_module_with_complete_content_and_pricing_exist(): void
    {
        $tutor = $this->tutor();
        $subject = $this->approveSubjectFor($tutor);
        $course = $tutor->tutorProfile->selfPacedCourses()->create([
            'title' => 'Ready Course',
            'subject_id' => $subject->id,
            'price' => 49.99,
            'currency' => 'USD',
        ]);
        $module = $course->modules()->create(['title' => 'Introduction', 'position' => 0]);
        $module->activities()->create([
            'type' => 'rich_text',
            'title' => 'Welcome',
            'position' => 0,
            'required' => true,
            'content' => ['html' => '<p>Welcome!</p>'],
        ]);

        Sanctum::actingAs($tutor);
        $response = $this->patchJson("/api/tutor/self-paced-courses/{$course->id}/publish");

        $response->assertOk();
        $response->assertJsonPath('course.status', 'published');
    }

    public function test_required_activity_missing_content_blocks_publishing(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->tutorProfile->selfPacedCourses()->create([
            'title' => 'Incomplete Course',
            'price' => 10,
            'currency' => 'USD',
        ]);
        $module = $course->modules()->create(['title' => 'Introduction', 'position' => 0]);
        $module->activities()->create([
            'type' => 'rich_text',
            'title' => 'Welcome',
            'position' => 0,
            'required' => true,
            'content' => null,
        ]);

        Sanctum::actingAs($tutor);
        $response = $this->patchJson("/api/tutor/self-paced-courses/{$course->id}/publish");

        $response->assertStatus(422);
        $response->assertJsonFragment(['course' => ['Activity "Welcome" in module "Introduction" is missing required content.']]);
    }

    public function test_required_assessment_without_provider_blocks_publishing(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->tutorProfile->selfPacedCourses()->create([
            'title' => 'Quiz Course',
            'price' => 10,
            'currency' => 'USD',
        ]);
        $module = $course->modules()->create(['title' => 'Assessments', 'position' => 0]);
        $module->assessments()->create([
            'assessment_type' => 'chapter_test',
            'title' => 'Chapter 1 Test',
            'position' => 0,
            'required' => true,
        ]);

        Sanctum::actingAs($tutor);
        $response = $this->patchJson("/api/tutor/self-paced-courses/{$course->id}/publish");

        $response->assertStatus(422);
        $response->assertJsonFragment(['course' => ['Assessment "Chapter 1 Test" in module "Assessments" has no provider configured.']]);
    }

    public function test_tutor_cannot_manage_another_tutors_course(): void
    {
        $owner = $this->tutor();
        $course = $owner->tutorProfile->selfPacedCourses()->create(['title' => 'Owned Course']);

        $intruder = $this->tutor();
        Sanctum::actingAs($intruder);

        $this->getJson("/api/tutor/self-paced-courses/{$course->id}")->assertForbidden();
        $this->putJson("/api/tutor/self-paced-courses/{$course->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/tutor/self-paced-courses/{$course->id}")->assertForbidden();
    }

    public function test_index_only_lists_the_authenticated_tutors_courses(): void
    {
        $tutor = $this->tutor();
        $tutor->tutorProfile->selfPacedCourses()->create(['title' => 'Mine']);

        $other = $this->tutor();
        $other->tutorProfile->selfPacedCourses()->create(['title' => 'Not Mine']);

        Sanctum::actingAs($tutor);
        $response = $this->getJson('/api/tutor/self-paced-courses');

        $response->assertOk();
        $response->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.title', 'Mine');
    }
}
