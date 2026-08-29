<?php

namespace Tests\Feature\Admin;

use App\Models\Grade;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TutoringServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_tutoring_services_filtered_by_visibility(): void
    {
        $admin = User::factory()->admin()->create();
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10]);
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);

        $tutor->services()->create([
            'subject_id' => $subject->id, 'grade_id' => $grade->id,
            'service_category_id' => $category->id, 'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths', 'description' => 'Tutoring.', 'price' => 100, 'currency' => 'ZAR',
            'session_duration_minutes' => 60, 'sessions_included' => 4, 'validity_period_days' => 30,
            'max_students_per_session' => 1, 'visibility' => 'published',
        ]);
        $tutor->services()->create([
            'subject_id' => $subject->id, 'grade_id' => $grade->id,
            'service_category_id' => $category->id, 'session_format_id' => $format->id,
            'title' => 'Draft Service', 'description' => 'Tutoring.', 'price' => 100, 'currency' => 'ZAR',
            'session_duration_minutes' => 60, 'sessions_included' => 4, 'validity_period_days' => 30,
            'max_students_per_session' => 1, 'visibility' => 'draft',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/tutoring-services?visibility=published');

        $response->assertOk();
        $response->assertJsonCount(1, 'services');
        $response->assertJsonPath('services.0.title', 'Grade 10 Maths');
        $response->assertJsonPath('services.0.subject', 'Mathematics');
    }
}
