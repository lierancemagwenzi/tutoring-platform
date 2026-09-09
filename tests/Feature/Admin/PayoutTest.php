<?php

namespace Tests\Feature\Admin;

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

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $tutor->bankAccount()->create([
            'bank_name' => 'Test Bank', 'account_holder_name' => 'Test Tutor',
            'account_number' => '123456789', 'branch_code' => '000000', 'account_type' => 'savings',
        ]);

        return $tutor;
    }

    private function courseTransaction(TutorProfile $tutor): FinancialTransaction
    {
        $student = User::factory()->create();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Course', 'price' => 100, 'currency' => 'ZAR', 'status' => 'published', 'visibility' => 'public',
        ]);
        $order = Order::create([
            'student_id' => $student->id, 'order_number' => 'ORD-'.uniqid(),
            'status' => 'paid', 'currency' => 'ZAR', 'total_amount' => 100, 'discount_amount' => 0, 'final_amount' => 100,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_type' => 'course_offering', 'product_id' => $course->id,
            'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'total' => 100,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id, 'provider' => 'payfast', 'payment_reference' => 'PAY-'.uniqid(),
            'amount' => 100, 'currency' => 'ZAR', 'status' => 'successful', 'paid_at' => now(),
        ]);

        return FinancialTransaction::create([
            'payment_id' => $payment->id, 'order_id' => $order->id, 'order_item_id' => $item->id,
            'tutor_profile_id' => $tutor->id, 'student_id' => $student->id,
            'product_type' => 'course_offering', 'product_id' => $course->id,
            'gross_amount' => 100, 'currency' => 'ZAR', 'platform_fee_total' => 20, 'tutor_amount' => 80,
        ]);
    }

    private function bookingTransaction(TutorProfile $tutor, string $bookingStatus): FinancialTransaction
    {
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

        return FinancialTransaction::create([
            'payment_id' => $payment->id, 'order_id' => $order->id, 'order_item_id' => $item->id,
            'tutor_profile_id' => $tutor->id, 'student_id' => $student->id,
            'product_type' => 'tutoring_service_booking', 'product_id' => $booking->id,
            'gross_amount' => 200, 'currency' => 'ZAR', 'platform_fee_total' => 40, 'tutor_amount' => 160,
        ]);
    }

    public function test_course_earning_can_be_marked_paid_immediately(): void
    {
        $this->admin();
        $transaction = $this->courseTransaction($this->tutor());

        $response = $this->postJson("/api/admin/financial-transactions/{$transaction->id}/mark-paid");

        $response->assertOk();
        $response->assertJsonPath('transaction.payout_status', 'paid');
        $this->assertNotNull($transaction->fresh()->paid_at);
    }

    public function test_booking_earning_is_blocked_until_the_booking_is_completed(): void
    {
        $this->admin();
        $transaction = $this->bookingTransaction($this->tutor(), 'confirmed');

        $response = $this->postJson("/api/admin/financial-transactions/{$transaction->id}/mark-paid");

        $response->assertStatus(422);
        $this->assertSame('pending', $transaction->fresh()->payout_status->value);
    }

    public function test_booking_earning_can_be_marked_paid_once_the_booking_is_completed(): void
    {
        $admin = $this->admin();
        $transaction = $this->bookingTransaction($this->tutor(), 'completed');

        $response = $this->postJson("/api/admin/financial-transactions/{$transaction->id}/mark-paid");

        $response->assertOk();
        $response->assertJsonPath('transaction.payout_status', 'paid');
        $transaction->refresh();
        $this->assertSame('paid', $transaction->payout_status->value);
        $this->assertSame($admin->id, $transaction->paid_by);
        $this->assertDatabaseHas('admin_activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'financial_transaction.paid',
            'subject_type' => FinancialTransaction::class,
            'subject_id' => $transaction->id,
        ]);
    }

    public function test_an_earning_cannot_be_marked_paid_without_banking_details_on_file(): void
    {
        $this->admin();
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'No Bank Tutor']);
        $transaction = $this->courseTransaction($tutor);

        $response = $this->postJson("/api/admin/financial-transactions/{$transaction->id}/mark-paid");

        $response->assertStatus(422);
        $this->assertSame('pending', $transaction->fresh()->payout_status->value);
    }

    public function test_already_paid_earning_cannot_be_marked_paid_again(): void
    {
        $this->admin();
        $transaction = $this->courseTransaction($this->tutor());
        $transaction->update(['payout_status' => 'paid', 'paid_at' => now()]);

        $response = $this->postJson("/api/admin/financial-transactions/{$transaction->id}/mark-paid");

        $response->assertStatus(422);
    }

    public function test_admin_transaction_list_reports_eligible_for_payout(): void
    {
        $this->admin();
        $tutor = $this->tutor();
        $this->courseTransaction($tutor);
        $this->bookingTransaction($tutor, 'confirmed');

        $response = $this->getJson('/api/admin/financial-transactions');

        $response->assertOk();
        $byType = collect($response->json('transactions'))->keyBy('product_type');
        $this->assertTrue($byType['course_offering']['eligible_for_payout']);
        $this->assertFalse($byType['tutoring_service_booking']['eligible_for_payout']);
    }

    public function test_non_admin_cannot_mark_a_transaction_paid(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $transaction = $this->courseTransaction($this->tutor());
        Sanctum::actingAs($tutorUser);

        $response = $this->postJson("/api/admin/financial-transactions/{$transaction->id}/mark-paid");

        $response->assertForbidden();
    }
}
