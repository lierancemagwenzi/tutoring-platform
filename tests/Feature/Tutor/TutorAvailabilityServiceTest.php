<?php

namespace Tests\Feature\Tutor;

use App\Models\AvailabilityDate;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Booking\TutorAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TutorAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function tutorWithService(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);

        $service = $tutor->services()->create([
            'subject_id' => $subject->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths',
            'description' => 'x',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ]);

        return [$tutor, $service];
    }

    public function test_available_windows_matches_configured_availability_when_nothing_is_scheduled(): void
    {
        [$tutor] = $this->tutorWithService();
        $date = Carbon::now()->addDays(10)->format('Y-m-d');
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => $date]);
        $availabilityDate->slots()->create(['start_time' => '07:00', 'end_time' => '09:00']);
        $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '11:00']);

        $windows = app(TutorAvailabilityService::class)->availableWindowsFor($tutor, $date);

        $this->assertSame([
            ['start_time' => '07:00', 'end_time' => '09:00'],
            ['start_time' => '09:00', 'end_time' => '11:00'],
        ], $windows);
    }

    public function test_a_scheduled_session_is_subtracted_from_availability(): void
    {
        [$tutor, $service] = $this->tutorWithService();
        $date = Carbon::now()->addDays(10)->format('Y-m-d');
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => $date]);
        $availabilityDate->slots()->create(['start_time' => '07:00', 'end_time' => '09:00']);
        $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '11:00']);

        // The spec's literal example: 07:00-09:00 is already booked, so
        // only 09:00-11:00 should remain.
        TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'date' => $date,
            'start_time' => '07:00',
            'end_time' => '09:00',
            'status' => 'scheduled',
        ]);

        $availability = app(TutorAvailabilityService::class);

        $this->assertSame([
            ['start_time' => '09:00', 'end_time' => '11:00'],
        ], $availability->availableWindowsFor($tutor, $date));

        $this->assertFalse($availability->isWindowAvailable($tutor, $date, '07:00', '08:00'));
        $this->assertTrue($availability->isWindowAvailable($tutor, $date, '09:00', '10:00'));
    }

    public function test_a_partial_overlap_leaves_only_the_remaining_sub_window(): void
    {
        [$tutor, $service] = $this->tutorWithService();
        $date = Carbon::now()->addDays(10)->format('Y-m-d');
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => $date]);
        $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
        ]);

        $windows = app(TutorAvailabilityService::class)->availableWindowsFor($tutor, $date);

        $this->assertSame([
            ['start_time' => '09:00', 'end_time' => '10:00'],
            ['start_time' => '11:00', 'end_time' => '12:00'],
        ], $windows);
    }

    public function test_a_cancelled_session_does_not_occupy_availability(): void
    {
        [$tutor, $service] = $this->tutorWithService();
        $date = Carbon::now()->addDays(10)->format('Y-m-d');
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => $date]);
        $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '11:00']);

        TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'cancelled',
        ]);

        $windows = app(TutorAvailabilityService::class)->availableWindowsFor($tutor, $date);

        $this->assertSame([
            ['start_time' => '09:00', 'end_time' => '11:00'],
        ], $windows);
    }

    public function test_has_overlap_detects_any_intersecting_non_cancelled_session(): void
    {
        [$tutor, $service] = $this->tutorWithService();
        $date = Carbon::now()->addDays(10)->format('Y-m-d');

        TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $availability = app(TutorAvailabilityService::class);

        $this->assertTrue($availability->hasOverlap($tutor, $date, '09:30', '10:30'));
        $this->assertFalse($availability->hasOverlap($tutor, $date, '10:00', '11:00'));
    }
}
