<?php

namespace Tests\Feature\Commerce;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingOrderTest extends TestCase
{
    use RefreshDatabase;

    private function tutorWithService(array $overrides = []): array
    {
        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);

        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $service = $tutorProfile->services()->create(array_merge([
            'subject_id' => $subject->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 12 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ], $overrides));

        $availabilityDate = AvailabilityDate::create([
            'tutor_profile_id' => $tutorProfile->id,
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
        ]);

        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        return [$tutorUser, $tutorProfile, $service, $slot];
    }

    private function pendingBooking(TutorProfile $tutor, Service $service, $slot): Booking
    {
        $student = User::factory()->create();

        return Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'pending',
        ]);
    }

    public function test_accepting_a_booking_creates_an_order_and_marks_awaiting_payment(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->tutorWithService();
        $booking = $this->pendingBooking($tutorProfile, $service, $slot);
        Sanctum::actingAs($tutorUser);

        $this->patchJson("/api/tutor/booking-requests/{$booking->id}/accept")->assertOk();

        $booking->refresh();
        $this->assertSame('awaiting_payment', $booking->status->value);
        $this->assertNotNull($booking->order_id);

        $order = Order::find($booking->order_id);
        $this->assertSame('pending', $order->status->value);
        $this->assertSame($booking->student_id, $order->student_id);
        $this->assertSame((float) $service->price, (float) $order->final_amount);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame('tutoring_service_booking', $order->items->first()->product_type);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pay_now_creates_a_payment_and_returns_payfast_fields_for_an_awaiting_payment_booking(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->tutorWithService();
        $booking = $this->pendingBooking($tutorProfile, $service, $slot);
        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/booking-requests/{$booking->id}/accept")->assertOk();
        $booking->refresh();

        Sanctum::actingAs($booking->student);

        $response = $this->getJson("/api/orders/{$booking->order_id}/pay");

        $response->assertOk();
        $response->assertJsonStructure(['process_url', 'fields' => ['merchant_id', 'merchant_key', 'm_payment_id', 'amount', 'signature']]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('pending', Payment::first()->status->value);
    }

    public function test_retrying_a_failed_payment_reuses_the_order_and_creates_a_new_payment_not_a_new_order(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->tutorWithService();
        $booking = $this->pendingBooking($tutorProfile, $service, $slot);
        Sanctum::actingAs($tutorUser);
        $this->patchJson("/api/tutor/booking-requests/{$booking->id}/accept")->assertOk();
        $booking->refresh();

        Sanctum::actingAs($booking->student);

        $this->getJson("/api/orders/{$booking->order_id}/pay")->assertOk();
        Payment::first()->update(['status' => 'failed']);

        $this->getJson("/api/orders/{$booking->order_id}/pay")->assertOk();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame('pending', Payment::latest('id')->first()->status->value);
    }
}
