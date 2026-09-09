<?php

namespace Tests\Feature\Tutor;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Subject $unassignedSubject;

    private Curriculum $curriculum;

    private Grade $grade;

    private Grade $unassignedGrade;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->unassignedSubject = Subject::create(['name' => 'Physics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->unassignedGrade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
    }

    private function tutorWithApprovedSubject(): User
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $this->subject->id,
            'status' => 'approved',
        ]);

        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $this->grade->id]);

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'A complete introduction to algebraic concepts.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
        ], $overrides);
    }

    private function createCourse(TutorProfile $tutorProfile, array $overrides = []): Course
    {
        return $tutorProfile->courses()->create(array_merge([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'A complete introduction to algebraic concepts.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_tutor_can_create_a_course(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/courses', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('course.title', 'Algebra Fundamentals');
        $response->assertJsonPath('course.status', 'draft');
        $response->assertJsonPath('course.subject.name', 'Mathematics');

        $this->assertDatabaseHas('courses', [
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'title' => 'Algebra Fundamentals',
        ]);
    }

    public function test_tutor_can_upload_thumbnail_and_cover_image(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/courses', $this->validPayload([
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg'),
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]));

        $response->assertCreated();
        $this->assertNotNull($response->json('course.thumbnail_url'));
        $this->assertNotNull($response->json('course.cover_image_url'));
    }

    public function test_tutor_cannot_create_a_course_for_a_subject_they_do_not_teach(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/courses', $this->validPayload(['subject_id' => $this->unassignedSubject->id]));

        $response->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_tutor_cannot_create_a_course_for_a_grade_not_assigned_to_that_subject(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/courses', $this->validPayload(['grade_id' => $this->unassignedGrade->id]));

        $response->assertUnprocessable()->assertJsonValidationErrors('grade_id');
    }

    public function test_tutor_cannot_create_a_course_for_a_subject_pending_approval(): void
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id]);
        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $this->subject->id,
            'status' => 'pending',
        ]);
        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $this->grade->id]);

        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/tutor/courses', $this->validPayload());

        $response->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_tutor_only_sees_their_own_courses(): void
    {
        $tutorA = $this->tutorWithApprovedSubject();
        $tutorB = $this->tutorWithApprovedSubject();

        $this->createCourse($tutorA->tutorProfile, ['title' => 'Tutor A Course']);
        $this->createCourse($tutorB->tutorProfile, ['title' => 'Tutor B Course']);

        Sanctum::actingAs($tutorA);

        $response = $this->getJson('/api/tutor/courses');

        $response->assertOk()->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.title', 'Tutor A Course');
    }

    public function test_tutor_cannot_view_another_tutors_course(): void
    {
        $owner = $this->tutorWithApprovedSubject();
        $other = $this->tutorWithApprovedSubject();
        $course = $this->createCourse($owner->tutorProfile);

        Sanctum::actingAs($other);

        $response = $this->getJson("/api/tutor/courses/{$course->id}");

        $response->assertForbidden();
    }

    public function test_tutor_can_update_own_course(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        $course = $this->createCourse($tutor->tutorProfile);

        Sanctum::actingAs($tutor);

        $response = $this->putJson("/api/tutor/courses/{$course->id}", $this->validPayload([
            'title' => 'Updated Title',
            'status' => 'draft',
        ]));

        $response->assertOk()->assertJsonPath('course.title', 'Updated Title');
    }

    public function test_tutor_cannot_update_another_tutors_course(): void
    {
        $owner = $this->tutorWithApprovedSubject();
        $other = $this->tutorWithApprovedSubject();
        $course = $this->createCourse($owner->tutorProfile);

        Sanctum::actingAs($other);

        $response = $this->putJson("/api/tutor/courses/{$course->id}", $this->validPayload(['status' => 'draft']));

        $response->assertForbidden();
    }

    public function test_tutor_can_delete_a_draft_course(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        $course = $this->createCourse($tutor->tutorProfile, ['status' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->deleteJson("/api/tutor/courses/{$course->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_tutor_cannot_delete_a_published_course(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        $course = $this->createCourse($tutor->tutorProfile, ['status' => 'published']);

        Sanctum::actingAs($tutor);

        $response = $this->deleteJson("/api/tutor/courses/{$course->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_tutor_can_archive_a_course(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        $course = $this->createCourse($tutor->tutorProfile, ['status' => 'published']);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/courses/{$course->id}/archive");

        $response->assertOk()->assertJsonPath('course.status', 'archived');
    }

    public function test_tutor_cannot_archive_another_tutors_course(): void
    {
        $owner = $this->tutorWithApprovedSubject();
        $other = $this->tutorWithApprovedSubject();
        $course = $this->createCourse($owner->tutorProfile, ['status' => 'published']);

        Sanctum::actingAs($other);

        $response = $this->patchJson("/api/tutor/courses/{$course->id}/archive");

        $response->assertForbidden();
    }
}
