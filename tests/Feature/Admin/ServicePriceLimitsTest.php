<?php

namespace Tests\Feature\Admin;

use App\Models\Grade;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use App\Services\Admin\PlatformSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicePriceLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function tutorWithProfile(Subject $subject, Grade $grade): User
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $user->id]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id, 'subject_id' => $subject->id, 'status' => 'approved',
        ]);
        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $grade->id]);

        $tutorProfile->bankAccount()->create([
            'bank_name' => 'Test Bank', 'account_holder_name' => 'Test Tutor',
            'account_number' => '123456789', 'branch_code' => '000000', 'account_type' => 'savings',
        ]);

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Subject $subject, Grade $grade, ServiceCategory $category, SessionFormat $format, float $price): array
    {
        return [
            'subject_id' => $subject->id, 'grade_id' => $grade->id,
            'service_category_id' => $category->id, 'session_format_id' => $format->id,
            'title' => 'Test Service', 'description' => 'A test service description.',
            'price' => $price, 'currency' => 'ZAR',
            'session_duration_minutes' => 60, 'sessions_included' => 1,
            'validity_period_days' => 30, 'max_students_per_session' => 1,
        ];
    }

    public function test_price_below_configured_minimum_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        app(PlatformSettingService::class)->set('pricing.min_tutor_price', 150, $admin);

        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $tutor = $this->tutorWithProfile($subject, $grade);
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->payload($subject, $grade, $category, $format, 100));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('price');
    }

    public function test_price_above_configured_maximum_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        app(PlatformSettingService::class)->set('pricing.max_tutor_price', 1000, $admin);

        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $tutor = $this->tutorWithProfile($subject, $grade);
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->payload($subject, $grade, $category, $format, 1500));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('price');
    }

    public function test_price_within_bounds_is_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        app(PlatformSettingService::class)->set('pricing.min_tutor_price', 150, $admin);
        app(PlatformSettingService::class)->set('pricing.max_tutor_price', 1000, $admin);

        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $tutor = $this->tutorWithProfile($subject, $grade);
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->payload($subject, $grade, $category, $format, 350));

        $response->assertCreated();
    }

    public function test_price_limits_disabled_when_not_configured(): void
    {
        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $tutor = $this->tutorWithProfile($subject, $grade);
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/services', $this->payload($subject, $grade, $category, $format, 5));

        $response->assertCreated();
    }
}
