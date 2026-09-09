<?php

namespace Tests\Feature\Tutor;

use App\Enums\TutorSubjectStatus;
use App\Models\Grade;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankingDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function tutorWithApprovedSubject(): User
    {
        $tutorUser = User::factory()->tutor()->create();
        $profile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $subject = Subject::create(['name' => 'Mathematics']);
        TutorSubject::create(['tutor_profile_id' => $profile->id, 'subject_id' => $subject->id, 'status' => TutorSubjectStatus::Approved]);

        return $tutorUser;
    }

    private function validPayload(): array
    {
        return [
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Test Tutor',
            'account_number' => '1234567890',
            'branch_code' => '000001',
            'account_type' => 'savings',
        ];
    }

    public function test_tutor_sees_no_banking_details_before_setting_any_up(): void
    {
        $tutorUser = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/tutor/banking-details');

        $response->assertOk();
        $response->assertJsonPath('bank_account', null);
    }

    public function test_tutor_can_create_and_view_their_own_banking_details(): void
    {
        $tutorUser = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutorUser);

        $store = $this->putJson('/api/tutor/banking-details', $this->validPayload());
        $store->assertOk();
        $store->assertJsonPath('bank_account.account_number', '1234567890');

        $show = $this->getJson('/api/tutor/banking-details');
        $show->assertJsonPath('bank_account.bank_name', 'Test Bank');
        $show->assertJsonPath('bank_account.account_number', '1234567890');
    }

    public function test_updating_banking_details_replaces_the_existing_record_not_a_second_one(): void
    {
        $tutorUser = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutorUser);

        $this->putJson('/api/tutor/banking-details', $this->validPayload())->assertOk();
        $this->putJson('/api/tutor/banking-details', array_merge($this->validPayload(), ['bank_name' => 'New Bank']))->assertOk();

        $this->assertDatabaseCount('tutor_bank_accounts', 1);
        $this->assertDatabaseHas('tutor_bank_accounts', ['bank_name' => 'New Bank']);
    }

    public function test_account_number_is_encrypted_at_rest(): void
    {
        $tutorUser = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutorUser);

        $this->putJson('/api/tutor/banking-details', $this->validPayload())->assertOk();

        $rawValue = DB::table('tutor_bank_accounts')->value('account_number');

        $this->assertNotSame('1234567890', $rawValue);
        $this->assertStringNotContainsString('1234567890', $rawValue);
    }

    public function test_tutor_without_banking_details_cannot_create_a_service(): void
    {
        $tutorUser = $this->tutorWithApprovedSubject();
        $tutor = $tutorUser->tutorProfile;
        $subject = $tutor->tutorSubjects()->first()->subject;
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10]);
        $tutor->tutorSubjects()->first()->tutorSubjectGrades()->create(['grade_id' => $grade->id]);

        Sanctum::actingAs($tutorUser);

        $response = $this->postJson('/api/tutor/services', [
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths',
            'description' => 'Tutoring.',
            'price' => 100,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('banking_details');
    }

    public function test_tutor_can_create_a_service_once_banking_details_exist(): void
    {
        $tutorUser = $this->tutorWithApprovedSubject();
        $tutor = $tutorUser->tutorProfile;
        $subject = $tutor->tutorSubjects()->first()->subject;
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10]);
        $tutor->tutorSubjects()->first()->tutorSubjectGrades()->create(['grade_id' => $grade->id]);

        Sanctum::actingAs($tutorUser);
        $this->putJson('/api/tutor/banking-details', $this->validPayload())->assertOk();

        $response = $this->postJson('/api/tutor/services', [
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths',
            'description' => 'Tutoring.',
            'price' => 100,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
        ]);

        $response->assertCreated();
    }

    public function test_tutor_without_banking_details_cannot_create_a_self_paced_course(): void
    {
        $tutorUser = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutorUser);

        $response = $this->postJson('/api/tutor/self-paced-courses', ['title' => 'My Course']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('banking_details');
    }
}
