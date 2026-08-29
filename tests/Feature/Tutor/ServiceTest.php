<?php

namespace Tests\Feature\Tutor;

use App\Models\AssessmentType;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\LearningResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Subject $unassignedSubject;

    private ServiceCategory $category;

    private SessionFormat $sessionFormat;

    private LearningResource $learningResource;

    private AssessmentType $assessmentType;

    private Curriculum $curriculum;

    private Grade $grade;

    private Grade $unassignedGrade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->unassignedSubject = Subject::create(['name' => 'Physics', 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->sessionFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $this->learningResource = LearningResource::create(['name' => 'Study Notes', 'is_active' => true]);
        $this->assessmentType = AssessmentType::create(['name' => 'Quiz', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->unassignedGrade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
    }

    private function tutorWithProfile(): User
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $user->id]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $this->subject->id,
            'status' => 'approved',
        ]);
        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $this->grade->id]);

        // Creating a service now requires banking details on file (see
        // BankingEligibilityService) — every test in this file needs one.
        $tutorProfile->bankAccount()->create([
            'bank_name' => 'Test Bank', 'account_holder_name' => 'Test Tutor',
            'account_number' => '123456789', 'branch_code' => '000000', 'account_type' => 'savings',
        ]);

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $this->subject->id,
            'grade_id' => $this->grade->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->sessionFormat->id,
            'title' => 'Grade 12 Maths Private Lesson',
            'description' => 'One-on-one private tutoring focused on exam technique.',
            'price' => 350,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'learning_resource_ids' => [$this->learningResource->id],
            'assessment_type_ids' => [$this->assessmentType->id],
            'curriculum_ids' => [$this->curriculum->id],
        ], $overrides);
    }

    private function createService(TutorProfile $tutorProfile, array $overrides = []): Service
    {
        $service = $tutorProfile->services()->create(array_merge([
            'subject_id' => $this->subject->id,
            'grade_id' => $this->grade->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->sessionFormat->id,
            'title' => 'Grade 12 Maths Private Lesson',
            'description' => 'One-on-one private tutoring focused on exam technique.',
            'price' => 350,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'draft',
        ], $overrides));

        $service->learningResources()->sync([$this->learningResource->id]);

        return $service;
    }

    public function test_tutor_can_create_a_service(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('service.title', 'Grade 12 Maths Private Lesson');
        $response->assertJsonPath('service.visibility', 'draft');
        $response->assertJsonPath('service.subject.name', 'Mathematics');
        $response->assertJsonPath('service.category.name', 'Private Lesson');
        $response->assertJsonPath('service.session_format.name', 'Online');
        $response->assertJsonCount(1, 'service.learning_resources');
        $response->assertJsonCount(1, 'service.assessment_types');
        $response->assertJsonCount(1, 'service.curricula');

        $this->assertDatabaseHas('services', [
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'title' => 'Grade 12 Maths Private Lesson',
        ]);
    }

    public function test_tutor_cannot_create_a_service_for_a_subject_they_do_not_teach(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->validPayload(['subject_id' => $this->unassignedSubject->id]));

        $response->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_tutor_cannot_update_a_service_to_a_subject_they_do_not_teach(): void
    {
        $tutor = $this->tutorWithProfile();
        $service = $this->createService($tutor->tutorProfile);

        Sanctum::actingAs($tutor);

        $response = $this->putJson(
            "/api/tutor/services/{$service->id}",
            $this->validPayload(['subject_id' => $this->unassignedSubject->id, 'visibility' => 'draft']),
        );

        $response->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_tutor_cannot_create_a_service_for_a_grade_not_assigned_to_that_subject(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->validPayload(['grade_id' => $this->unassignedGrade->id]));

        $response->assertUnprocessable()->assertJsonValidationErrors('grade_id');
    }

    public function test_tutor_cannot_create_a_service_for_a_subject_pending_approval(): void
    {
        $tutor = $this->tutorWithProfile();
        TutorSubject::create([
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'subject_id' => $this->unassignedSubject->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->validPayload(['subject_id' => $this->unassignedSubject->id]));

        $response->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_tutor_cannot_create_a_service_for_a_rejected_subject(): void
    {
        $tutor = $this->tutorWithProfile();
        TutorSubject::create([
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'subject_id' => $this->unassignedSubject->id,
            'status' => 'rejected',
        ]);

        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->validPayload(['subject_id' => $this->unassignedSubject->id]));

        $response->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_price_must_be_greater_than_zero(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->validPayload(['price' => 0]));

        $response->assertUnprocessable()->assertJsonValidationErrors('price');
    }

    public function test_required_fields_are_validated(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', []);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'subject_id',
            'grade_id',
            'service_category_id',
            'session_format_id',
            'title',
            'description',
            'price',
            'currency',
            'session_duration_minutes',
            'sessions_included',
            'validity_period_days',
            'max_students_per_session',
        ]);
    }

    public function test_tutor_only_sees_their_own_services(): void
    {
        $tutorA = $this->tutorWithProfile();
        $tutorB = $this->tutorWithProfile();

        $this->createService($tutorA->tutorProfile, ['title' => 'Tutor A Service']);
        $this->createService($tutorB->tutorProfile, ['title' => 'Tutor B Service']);

        Sanctum::actingAs($tutorA);

        $response = $this->getJson('/api/tutor/services');

        $response->assertOk()->assertJsonCount(1, 'services');
        $response->assertJsonPath('services.0.title', 'Tutor A Service');
    }

    public function test_tutor_can_view_own_service(): void
    {
        $tutor = $this->tutorWithProfile();
        $service = $this->createService($tutor->tutorProfile);

        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/services/{$service->id}");

        $response->assertOk()->assertJsonPath('service.id', $service->id);
    }

    public function test_tutor_cannot_view_another_tutors_service(): void
    {
        $owner = $this->tutorWithProfile();
        $other = $this->tutorWithProfile();
        $service = $this->createService($owner->tutorProfile);

        Sanctum::actingAs($other);

        $response = $this->getJson("/api/tutor/services/{$service->id}");

        $response->assertForbidden();
    }

    public function test_tutor_can_update_a_service_and_resync_resources(): void
    {
        $tutor = $this->tutorWithProfile();
        $service = $this->createService($tutor->tutorProfile);

        $newResource = LearningResource::create(['name' => 'Worksheets', 'is_active' => true]);

        Sanctum::actingAs($tutor);

        $response = $this->putJson("/api/tutor/services/{$service->id}", $this->validPayload([
            'title' => 'Updated Title',
            'price' => 400,
            'visibility' => 'draft',
            'learning_resource_ids' => [$newResource->id],
        ]));

        $response->assertOk();
        $response->assertJsonPath('service.title', 'Updated Title');
        $response->assertJsonPath('service.price', '400.00');
        $response->assertJsonCount(1, 'service.learning_resources');
        $response->assertJsonPath('service.learning_resources.0.name', 'Worksheets');
    }

    public function test_tutor_cannot_update_another_tutors_service(): void
    {
        $owner = $this->tutorWithProfile();
        $other = $this->tutorWithProfile();
        $service = $this->createService($owner->tutorProfile);

        Sanctum::actingAs($other);

        $response = $this->putJson("/api/tutor/services/{$service->id}", $this->validPayload());

        $response->assertForbidden();
    }

    public function test_tutor_can_publish_a_draft_service(): void
    {
        $tutor = $this->tutorWithProfile();
        $service = $this->createService($tutor->tutorProfile, ['visibility' => 'draft']);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/services/{$service->id}/publish");

        $response->assertOk()->assertJsonPath('service.visibility', 'published');
    }

    public function test_tutor_can_pause_a_published_service(): void
    {
        $tutor = $this->tutorWithProfile();
        $service = $this->createService($tutor->tutorProfile, ['visibility' => 'published']);

        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/services/{$service->id}/pause");

        $response->assertOk()->assertJsonPath('service.visibility', 'paused');
    }

    public function test_tutor_cannot_publish_another_tutors_service(): void
    {
        $owner = $this->tutorWithProfile();
        $other = $this->tutorWithProfile();
        $service = $this->createService($owner->tutorProfile);

        Sanctum::actingAs($other);

        $response = $this->patchJson("/api/tutor/services/{$service->id}/publish");

        $response->assertForbidden();
    }

    public function test_tutor_cannot_pause_another_tutors_service(): void
    {
        $owner = $this->tutorWithProfile();
        $other = $this->tutorWithProfile();
        $service = $this->createService($owner->tutorProfile, ['visibility' => 'published']);

        Sanctum::actingAs($other);

        $response = $this->patchJson("/api/tutor/services/{$service->id}/pause");

        $response->assertForbidden();
    }
}
