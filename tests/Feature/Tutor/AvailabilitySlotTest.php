<?php

namespace Tests\Feature\Tutor;

use App\Models\AvailabilitySlot;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvailabilitySlotTest extends TestCase
{
    use RefreshDatabase;

    private string $futureDate;

    private string $futureMonth;

    private string $otherMonthDate;

    private string $pastDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->futureDate = Carbon::now()->addDays(30)->format('Y-m-d');
        $this->futureMonth = Carbon::now()->addDays(30)->format('Y-m');
        $this->otherMonthDate = Carbon::now()->addDays(30)->addMonthNoOverflow()->format('Y-m-d');
        $this->pastDate = Carbon::now()->subDay()->format('Y-m-d');
    }

    private function tutorWithProfile(): User
    {
        $user = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id]);

        return $user->fresh();
    }

    private function createSlot(TutorProfile $tutorProfile, string $date, string $startTime, string $endTime): AvailabilitySlot
    {
        $availabilityDate = $tutorProfile->availabilityDates()->firstOrCreate(['date' => $date]);

        return $availabilityDate->slots()->create([
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    public function test_tutor_can_list_availability_dates_for_a_month(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '11:00');
        $this->createSlot($tutor->tutorProfile, $this->futureDate, '14:00', '17:00');

        // Different month — should not appear.
        $this->createSlot($tutor->tutorProfile, $this->otherMonthDate, '09:00', '11:00');

        $response = $this->getJson("/api/tutor/availability?month={$this->futureMonth}");

        $response->assertOk()->assertJsonCount(1, 'dates');
        $response->assertJsonPath('dates.0.date', $this->futureDate);
        $response->assertJsonCount(2, 'dates.0.slots');
        $response->assertJsonPath('dates.0.slots.0.start_time', '09:00');
        $response->assertJsonPath('dates.0.slots.1.start_time', '14:00');
    }

    public function test_tutor_can_create_an_availability_slot(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/availability', [
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('date.date', $this->futureDate);
        $response->assertJsonCount(1, 'date.slots');
        $response->assertJsonPath('date.slots.0.start_time', '09:00');
        $response->assertJsonPath('date.slots.0.end_time', '11:00');

        $this->assertDatabaseHas('availability_dates', [
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'date' => $this->futureDate,
        ]);
    }

    public function test_adding_a_second_slot_on_the_same_date_reuses_the_date_row(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '11:00');

        $response = $this->postJson('/api/tutor/availability', [
            'date' => $this->futureDate,
            'start_time' => '14:00',
            'end_time' => '17:00',
        ]);

        $response->assertCreated()->assertJsonCount(2, 'date.slots');
        $this->assertDatabaseCount('availability_dates', 1);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/availability', [
            'date' => $this->futureDate,
            'start_time' => '11:00',
            'end_time' => '09:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('end_time');
    }

    public function test_past_dates_cannot_be_selected(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/availability', [
            'date' => $this->pastDate,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_overlapping_slots_on_the_same_day_are_rejected(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '11:00');

        $response = $this->postJson('/api/tutor/availability', [
            'date' => $this->futureDate,
            'start_time' => '10:30',
            'end_time' => '12:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    public function test_non_overlapping_slots_on_the_same_day_are_allowed(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '12:00');

        $response = $this->postJson('/api/tutor/availability', [
            'date' => $this->futureDate,
            'start_time' => '14:00',
            'end_time' => '17:00',
        ]);

        $response->assertCreated();
    }

    public function test_tutor_can_update_an_availability_slot(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $slot = $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '11:00');

        $response = $this->putJson("/api/tutor/availability/{$slot->id}", [
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('date.slots.0.start_time', '10:00');
        $response->assertJsonPath('date.slots.0.end_time', '12:00');
    }

    public function test_updating_a_slot_to_overlap_another_is_rejected(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '11:00');
        $slot = $this->createSlot($tutor->tutorProfile, $this->futureDate, '14:00', '17:00');

        $response = $this->putJson("/api/tutor/availability/{$slot->id}", [
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    public function test_tutor_can_delete_an_availability_slot(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $slot = $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '11:00');

        $response = $this->deleteJson("/api/tutor/availability/{$slot->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('availability_slots', ['id' => $slot->id]);
    }

    public function test_deleting_the_last_slot_on_a_date_removes_the_date_row_too(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $slot = $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '11:00');
        $availabilityDateId = $slot->availability_date_id;

        $response = $this->deleteJson("/api/tutor/availability/{$slot->id}");

        $response->assertOk()->assertJsonPath('date', null);
        $this->assertDatabaseMissing('availability_dates', ['id' => $availabilityDateId]);
    }

    public function test_deleting_one_of_several_slots_keeps_the_date_row(): void
    {
        $tutor = $this->tutorWithProfile();
        Sanctum::actingAs($tutor);

        $slot = $this->createSlot($tutor->tutorProfile, $this->futureDate, '09:00', '11:00');
        $this->createSlot($tutor->tutorProfile, $this->futureDate, '14:00', '17:00');
        $availabilityDateId = $slot->availability_date_id;

        $response = $this->deleteJson("/api/tutor/availability/{$slot->id}");

        $response->assertOk()->assertJsonCount(1, 'date.slots');
        $this->assertDatabaseHas('availability_dates', ['id' => $availabilityDateId]);
    }

    public function test_a_tutor_cannot_update_another_tutors_slot(): void
    {
        $ownerTutor = $this->tutorWithProfile();
        $otherTutor = $this->tutorWithProfile();

        $slot = $this->createSlot($ownerTutor->tutorProfile, $this->futureDate, '09:00', '11:00');

        Sanctum::actingAs($otherTutor);

        $response = $this->putJson("/api/tutor/availability/{$slot->id}", [
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response->assertForbidden();
    }

    public function test_a_tutor_cannot_delete_another_tutors_slot(): void
    {
        $ownerTutor = $this->tutorWithProfile();
        $otherTutor = $this->tutorWithProfile();

        $slot = $this->createSlot($ownerTutor->tutorProfile, $this->futureDate, '09:00', '11:00');

        Sanctum::actingAs($otherTutor);

        $response = $this->deleteJson("/api/tutor/availability/{$slot->id}");

        $response->assertForbidden();
    }
}
