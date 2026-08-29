<?php

namespace Tests\Feature\Student;

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

class FinancialTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function courseTransaction(User $student): FinancialTransaction
    {
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Algebra Foundations', 'price' => 249, 'currency' => 'ZAR', 'status' => 'published', 'visibility' => 'public',
        ]);
        $order = Order::create([
            'student_id' => $student->id, 'order_number' => 'ORD-'.uniqid(),
            'status' => 'paid', 'currency' => 'ZAR', 'total_amount' => 249, 'discount_amount' => 0, 'final_amount' => 249,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_type' => 'course_offering', 'product_id' => $course->id,
            'quantity' => 1, 'unit_price' => 249, 'discount' => 0, 'total' => 249,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id, 'provider' => 'payfast', 'payment_reference' => 'PAY-'.uniqid(),
            'amount' => 249, 'currency' => 'ZAR', 'status' => 'successful', 'paid_at' => now(),
        ]);

        return FinancialTransaction::create([
            'payment_id' => $payment->id, 'order_id' => $order->id, 'order_item_id' => $item->id,
            'tutor_profile_id' => $tutor->id, 'student_id' => $student->id,
            'product_type' => 'course_offering', 'product_id' => $course->id,
            'gross_amount' => 249, 'currency' => 'ZAR', 'platform_fee_total' => 49.80, 'tutor_amount' => 199.20,
        ]);
    }

    private function bookingTransaction(User $student): FinancialTransaction
    {
        $tutor = $this->tutor();
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
            'price' => 200, 'currency' => 'ZAR', 'status' => 'confirmed',
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

    public function test_student_can_list_their_own_financial_transactions(): void
    {
        $student = User::factory()->create();
        $this->courseTransaction($student);
        $this->bookingTransaction($student);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/financial-transactions');

        $response->assertOk();
        $response->assertJsonCount(2, 'transactions');
        $titles = collect($response->json('transactions'))->pluck('product_title');
        $this->assertTrue($titles->contains('Algebra Foundations'));
        $this->assertTrue($titles->contains('Grade 10 Maths'));
    }

    public function test_transaction_resource_does_not_expose_internal_fee_breakdown(): void
    {
        $student = User::factory()->create();
        $this->courseTransaction($student);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/financial-transactions');

        $response->assertOk();
        $transaction = $response->json('transactions.0');
        $this->assertArrayNotHasKey('platform_fee_total', $transaction);
        $this->assertArrayNotHasKey('tutor_amount', $transaction);
        $this->assertArrayNotHasKey('payout_status', $transaction);
        $this->assertSame('249.00', $transaction['gross_amount']);
    }

    public function test_a_student_only_sees_their_own_transactions(): void
    {
        $student = User::factory()->create();
        $this->courseTransaction($student);
        $otherStudent = User::factory()->create();
        $this->courseTransaction($otherStudent);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/financial-transactions');

        $response->assertOk();
        $response->assertJsonCount(1, 'transactions');
    }

    public function test_transactions_can_be_filtered_by_date_range(): void
    {
        $student = User::factory()->create();
        $old = $this->courseTransaction($student);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        $this->bookingTransaction($student);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/student/financial-transactions?from='.now()->subDays(2)->toDateString());

        $response->assertOk();
        $response->assertJsonCount(1, 'transactions');
        $response->assertJsonPath('transactions.0.product_title', 'Grade 10 Maths');
    }
}
