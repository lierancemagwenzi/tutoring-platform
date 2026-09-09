<?php

namespace Tests\Feature\Tutor;

use App\Enums\UserStatus;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TutorSubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_a_subject_defaults_to_pending_status(): void
    {
        $user = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id]);
        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);

        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/tutor/subjects', [
            'subject_id' => $subject->id,
            'grade_ids' => [$grade->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('subject.status', 'pending');
        $this->assertDatabaseHas('tutor_subjects', [
            'subject_id' => $subject->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_tutor_pending_approval_cannot_add_a_subject(): void
    {
        $user = User::factory()->tutor()->create(['status' => UserStatus::Pending]);
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id]);
        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);

        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/tutor/subjects', [
            'subject_id' => $subject->id,
            'grade_ids' => [$grade->id],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('tutor_subjects', ['subject_id' => $subject->id]);
    }

    public function test_tutor_subject_resource_exposes_status(): void
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id]);
        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $subject->id,
            'status' => 'approved',
        ]);
        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $grade->id]);

        Sanctum::actingAs($user->fresh());

        $response = $this->getJson('/api/tutor/subjects');

        $response->assertOk();
        $response->assertJsonPath('subjects.0.status', 'approved');
    }
}
