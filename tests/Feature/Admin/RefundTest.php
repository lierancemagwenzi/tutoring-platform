<?php

namespace Tests\Feature\Admin;

use App\Enums\PayoutStatus;
use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * @return array{booking: Booking, transaction: FinancialTransaction}
     */
    private function confirmedBookingWithTransaction(string $bookingStatus = 'confirmed'): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $student = User::factory()->create();
        $subject = Subject::create(['name' => 'Mathematics']);
        $category = ServiceCategory::create(['name' => 'Private Lesson']);
        $format = SessionFormat::create(['name' => 'Online']);
        $service = $tutor->services()->create([
            'subject_id' => $subject->id, 'service_category_id' => $category->id, 'session_format_id' => $format->id,
            'title' => 'Grade 10 Maths', 'description' => 'Tutoring.', 'price' => 200, 'currency' => 'ZAR',
            'session_duration_minutes' => 60, 'sessions_included' => 1, 'validity_period_days' => 30,
            'max_students_per_session' => 1, 'visibility' => 'published',
        ]);
        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => now()->addDays(5)->toDateString()]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '10:00']);
        $booking = Booking::create([
            'student_id' => $student->id, 'tutor_profile_id' => $tutor->id, 'service_id' => $service->id,
            'availability_slot_id' => $slot->id, 'date' => $availabilityDate->date, 'start_time' => '09:00', 'end_time' => '10:00',
            'price' => 200, 'currency' => 'ZAR', 'status' => $bookingStatus,
        ]);
        $order = Order::create([
            'student_id' => $student->id, 'order_number' => 'ORD-'.uniqid(),
            'status' => 'paid', 'currency' => 'ZAR', 'total_amount' => 200, 'discount_amount' => 0, 'final_amount' => 200,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_type' => 'tutoring_service_booking', 'product_id' => $booking->id,
            'quantity' => 1, 'unit_price' => 200, 'discount' => 0, 'total' => 200,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id, 'provider' => 'payfast', 'payment_reference' => 'PAY-'.uniqid(),
            'amount' => 200, 'currency' => 'ZAR', 'status' => 'successful', 'paid_at' => now(),
        ]);
        $transaction = FinancialTransaction::create([
            'payment_id' => $payment->id, 'order_id' => $order->id, 'order_item_id' => $item->id,
            'tutor_profile_id' => $tutor->id, 'student_id' => $student->id,
            'product_type' => 'tutoring_service_booking', 'product_id' => $booking->id,
            'gross_amount' => 200, 'currency' => 'ZAR', 'platform_fee_total' => 40, 'tutor_amount' => 160,
        ]);

        return ['booking' => $booking, 'transaction' => $transaction];
    }

    public function test_admin_can_cancel_and_refund_a_confirmed_booking(): void
    {
        $this->admin();
        $fixture = $this->confirmedBookingWithTransaction();

        $response = $this->postJson("/api/admin/bookings/{$fixture['booking']->id}/cancel", ['reason' => 'Tutor unavailable']);

        $response->assertOk();
        $this->assertSame('cancelled', $fixture['booking']->fresh()->status->value);

        $transaction = $fixture['transaction']->fresh();
        $this->assertSame('refunded', $transaction->refund_status->value);
        $this->assertNotNull($transaction->refunded_at);
        // Core snapshot amounts must never be mutated by a refund.
        $this->assertSame('200.00', (string) $transaction->gross_amount);
        $this->assertSame('40.00', (string) $transaction->platform_fee_total);
        $this->assertSame('160.00', (string) $transaction->tutor_amount);
    }

    public function test_refunding_a_transaction_that_was_already_paid_out_flags_it_adjusted(): void
    {
        $admin = $this->admin();
        $fixture = $this->confirmedBookingWithTransaction();
        $fixture['transaction']->update(['payout_status' => PayoutStatus::Paid, 'paid_at' => now(), 'paid_by' => $admin->id]);

        $response = $this->postJson("/api/admin/bookings/{$fixture['booking']->id}/cancel", ['reason' => 'Student complaint upheld']);

        $response->assertOk();
        $this->assertSame('adjusted', $fixture['transaction']->fresh()->payout_status->value);
    }

    public function test_refunded_transaction_is_no_longer_eligible_for_payout(): void
    {
        $this->admin();
        $fixture = $this->confirmedBookingWithTransaction();

        $this->postJson("/api/admin/bookings/{$fixture['booking']->id}/cancel", ['reason' => 'Duplicate booking']);

        $response = $this->getJson('/api/admin/financial-transactions');
        $response->assertOk();
        $this->assertFalse(collect($response->json('transactions'))->firstWhere('id', $fixture['transaction']->id)['eligible_for_payout']);
    }

    public function test_cannot_refund_the_same_transaction_twice(): void
    {
        $this->admin();
        $fixture = $this->confirmedBookingWithTransaction();

        $this->postJson("/api/admin/bookings/{$fixture['booking']->id}/cancel", ['reason' => 'First cancellation'])->assertOk();
        $fixture['booking']->update(['status' => 'confirmed']);

        $response = $this->postJson("/api/admin/bookings/{$fixture['booking']->id}/cancel", ['reason' => 'Second attempt']);

        $response->assertStatus(422);
    }

    public function test_pending_booking_cannot_be_cancelled_via_the_admin_refund_flow(): void
    {
        $this->admin();
        $fixture = $this->confirmedBookingWithTransaction('pending');

        $response = $this->postJson("/api/admin/bookings/{$fixture['booking']->id}/cancel", ['reason' => 'Not applicable yet']);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_cancel_a_booking(): void
    {
        $fixture = $this->confirmedBookingWithTransaction();
        Sanctum::actingAs($fixture['booking']->student);

        $response = $this->postJson("/api/admin/bookings/{$fixture['booking']->id}/cancel", ['reason' => 'Trying to self-refund']);

        $response->assertForbidden();
    }
}
