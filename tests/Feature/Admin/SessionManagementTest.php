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

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_sessions_with_meeting_status(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10]);
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);
        $service = $tutor->services()->create([
            'subject_id' => $subject->id, 'grade_id' => $grade->id,
            'service_category_id' => $category->id, 'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths', 'description' => 'Tutoring.', 'price' => 100, 'currency' => 'ZAR',
            'session_duration_minutes' => 60, 'sessions_included' => 4, 'validity_period_days' => 30,
            'max_students_per_session' => 1, 'visibility' => 'published',
        ]);
        $availabilityDate = $tutor->availabilityDates()->create(['date' => now()->addDay()->toDateString()]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '10:00']);

        $session = $tutor->teachingSessions()->create([
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $availabilityDate->date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);
        $session->sessionMeeting()->create([
            'provider' => 'google',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'status' => 'scheduled',
        ]);
        $booking = $service->bookings()->create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'availability_slot_id' => $slot->id,
            'date' => $availabilityDate->date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => 100,
            'currency' => 'ZAR',
            'status' => 'confirmed',
        ]);
        $booking->teachingSessions()->attach($session->id);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/sessions');

        $response->assertOk();
        $response->assertJsonPath('sessions.0.meeting_provider', 'google');
        $response->assertJsonPath('sessions.0.meeting_status', 'scheduled');
        $response->assertJsonPath('sessions.0.students.0', trim("{$student->first_name} {$student->last_name}"));
    }
}
