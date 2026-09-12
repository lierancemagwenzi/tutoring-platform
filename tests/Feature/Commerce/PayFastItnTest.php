<?php

namespace Tests\Feature\Commerce;

use App\Enums\FinancialRuleScope;
use App\Jobs\CreateSessionMeetingJob;
use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Enrollment;
use App\Models\FinancialRule;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PayFast\PayFastSignature;
use App\Services\Commerce\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayFastItnTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function orderForNewCourse(float $price = 200): Order
    {
        $student = User::factory()->create();
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Test Course',
            'price' => $price,
            'currency' => 'ZAR',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        return app(OrderService::class)->createForCourse($student, $course);
    }

    private function orderForNewBooking(float $price = 300): array
    {
        $tutor = $this->tutor();
        $subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $format = SessionFormat::create(['name' => 'Online', 'is_active' => true]);

        $service = $tutor->services()->create([
            'subject_id' => $subject->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Grade 12 Maths',
            'description' => 'Exam preparation.',
            'price' => $price,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ]);

        $availabilityDate = AvailabilityDate::create([
            'tutor_profile_id' => $tutor->id,
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
        ]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        $student = User::factory()->create();
        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $price,
            'currency' => 'ZAR',
            'status' => 'pending',
        ]);

        $order = app(OrderService::class)->createForBooking($booking);
        app(PaymentService::class)->createPendingForOrder($order);
        $booking->update(['order_id' => $order->id, 'status' => 'awaiting_payment']);

        return [$order->fresh(['items', 'latestPayment']), $booking->fresh()];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signedItnPayload(Order $order, array $overrides = []): array
    {
        $payment = $order->latestPayment;

        $payload = array_merge([
            'm_payment_id' => $payment->payment_reference,
            'pf_payment_id' => '123456',
            'payment_status' => 'COMPLETE',
            'amount_gross' => number_format((float) $order->final_amount, 2, '.', ''),
            'payment_method' => 'cc',
            'custom_str1' => (string) $order->id,
        ], $overrides);

        $payload['signature'] = app(PayFastSignature::class)->generateForVerification($payload, config('services.payfast.passphrase'));

        return $payload;
    }

    public function test_valid_itn_marks_payment_successful_order_paid_and_creates_enrollment(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $order = $this->orderForNewCourse();
        $payload = $this->signedItnPayload($order);

        $response = $this->postJson('/api/payfast/itn', $payload);

        $response->assertOk();

        $order->refresh();
        $payment = Payment::first();

        $this->assertSame('paid', $order->status->value);
        $this->assertSame('successful', $payment->status->value);
        $this->assertSame('123456', $payment->provider_reference);
        $this->assertNotNull($payment->paid_at);
        $this->assertDatabaseCount('enrollments', 1);

        $enrollment = Enrollment::first();
        $this->assertSame($order->student_id, $enrollment->student_id);

        // Default global rule (20% / R0) against a R200 course.
        $transaction = FinancialTransaction::first();
        $this->assertNotNull($transaction);
        $this->assertSame('40.00', (string) $transaction->platform_fee_total);
        $this->assertSame('160.00', (string) $transaction->tutor_amount);
        $this->assertSame('200.00', (string) $transaction->gross_amount);
    }

    public function test_valid_itn_with_empty_optional_fields_still_validates(): void
    {
        // PayFast's real ITN includes optional fields (item_description,
        // custom_str2-5, custom_int1-5) even when unused, as empty strings.
        // Its signature is computed over every field it sent, including
        // these empty ones — unlike outbound signing, which omits them.
        Http::fake(['*' => Http::response('VALID', 200)]);

        $order = $this->orderForNewCourse();
        $payload = $this->signedItnPayload($order, [
            'item_description' => '',
            'custom_str2' => '',
            'custom_str3' => '',
            'custom_int1' => '',
        ]);

        $response = $this->postJson('/api/payfast/itn', $payload);

        $response->assertOk();
        $this->assertSame('paid', $order->fresh()->status->value);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_itn_with_bad_signature_is_rejected_and_does_not_activate_enrollment(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $order = $this->orderForNewCourse();
        $payload = $this->signedItnPayload($order);
        $payload['amount_gross'] = '1.00'; // tampered after signing

        $response = $this->postJson('/api/payfast/itn', $payload);

        $response->assertOk();

        $order->refresh();
        $this->assertSame('pending', $order->status->value);
        $this->assertSame('pending', Payment::first()->status->value);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_itn_failing_payfast_server_side_validation_is_rejected(): void
    {
        Http::fake(['*' => Http::response('INVALID', 200)]);

        $order = $this->orderForNewCourse();
        $payload = $this->signedItnPayload($order);

        $response = $this->postJson('/api/payfast/itn', $payload);

        $response->assertOk();

        $order->refresh();
        $this->assertSame('pending', $order->status->value);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_itn_with_amount_mismatch_marks_payment_failed_not_successful(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $order = $this->orderForNewCourse(price: 200);
        $payload = $this->signedItnPayload($order, ['amount_gross' => '1.00']);

        $response = $this->postJson('/api/payfast/itn', $payload);

        $response->assertOk();

        $this->assertSame('pending', $order->fresh()->status->value);
        $this->assertSame('failed', Payment::first()->status->value);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_itn_is_idempotent_on_duplicate_valid_calls(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $order = $this->orderForNewCourse();
        $payload = $this->signedItnPayload($order);

        $this->postJson('/api/payfast/itn', $payload)->assertOk();
        $this->postJson('/api/payfast/itn', $payload)->assertOk();

        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_itn_for_unknown_payment_reference_is_ignored_safely(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $order = $this->orderForNewCourse();
        $payload = $this->signedItnPayload($order, ['m_payment_id' => 'does-not-exist']);

        $response = $this->postJson('/api/payfast/itn', $payload);

        $response->assertOk();
        $this->assertSame('pending', $order->fresh()->status->value);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_valid_itn_for_a_booking_order_confirms_the_booking_and_auto_schedules_the_first_session(): void
    {
        // Payment confirms the booking and immediately schedules its first
        // session from the date/time the student originally requested —
        // see BookingConfirmationService::scheduleFirstSession(). Any
        // further sessions in the purchased package are still scheduled
        // later, one at a time, by the tutor via SessionSchedulingService.
        Http::fake(['*' => Http::response('VALID', 200)]);
        Queue::fake();

        [$order, $booking] = $this->orderForNewBooking();
        $payload = $this->signedItnPayload($order);

        $response = $this->postJson('/api/payfast/itn', $payload);

        $response->assertOk();

        $booking->refresh();
        $this->assertSame('paid', $order->fresh()->status->value);
        $this->assertSame('confirmed', $booking->status->value);

        $this->assertDatabaseCount('teaching_sessions', 1);
        $this->assertDatabaseCount('session_meetings', 1);
        $this->assertTrue($booking->teachingSessions()->exists());

        Queue::assertPushed(CreateSessionMeetingJob::class);

        // Default global rule (20% / R0) against a R300 booking.
        $transaction = FinancialTransaction::first();
        $this->assertNotNull($transaction);
        $this->assertSame('60.00', (string) $transaction->platform_fee_total);
        $this->assertSame('240.00', (string) $transaction->tutor_amount);
    }

    public function test_duplicate_itn_for_a_booking_order_is_idempotent(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);
        Queue::fake();

        [$order, $booking] = $this->orderForNewBooking();
        $payload = $this->signedItnPayload($order);

        $this->postJson('/api/payfast/itn', $payload)->assertOk();
        $this->postJson('/api/payfast/itn', $payload)->assertOk();

        $this->assertSame('confirmed', $booking->fresh()->status->value);
        // Only the first ITN call's auto-scheduled session — the duplicate
        // is a no-op because the booking is already Confirmed by then.
        $this->assertDatabaseCount('teaching_sessions', 1);
    }

    public function test_duplicate_itn_does_not_create_a_second_financial_transaction(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $order = $this->orderForNewCourse();
        $payload = $this->signedItnPayload($order);

        $this->postJson('/api/payfast/itn', $payload)->assertOk();
        $this->postJson('/api/payfast/itn', $payload)->assertOk();

        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_tutor_scope_rule_overrides_the_global_rule(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $student = User::factory()->create();
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Test Course', 'price' => 200, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);
        FinancialRule::create([
            'scope' => FinancialRuleScope::Tutor, 'tutor_profile_id' => $tutor->id,
            'percentage' => 10, 'fixed_fee' => 25,
        ]);

        $order = app(OrderService::class)->createForCourse($student, $course);
        $payload = $this->signedItnPayload($order);

        $this->postJson('/api/payfast/itn', $payload)->assertOk();

        $transaction = FinancialTransaction::first();
        // 10% of 200 = 20, + R25 fixed = R45 platform, R155 tutor.
        $this->assertSame('45.00', (string) $transaction->platform_fee_total);
        $this->assertSame('155.00', (string) $transaction->tutor_amount);
    }

    public function test_course_scope_rule_overrides_both_tutor_and_global_rules(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $student = User::factory()->create();
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Test Course', 'price' => 200, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);
        FinancialRule::create([
            'scope' => FinancialRuleScope::Tutor, 'tutor_profile_id' => $tutor->id,
            'percentage' => 10, 'fixed_fee' => 25,
        ]);
        FinancialRule::create([
            'scope' => FinancialRuleScope::Course, 'self_paced_course_id' => $course->id,
            'percentage' => 12, 'fixed_fee' => 0,
        ]);

        $order = app(OrderService::class)->createForCourse($student, $course);
        $payload = $this->signedItnPayload($order);

        $this->postJson('/api/payfast/itn', $payload)->assertOk();

        $transaction = FinancialTransaction::first();
        // Course rule (12% / R0) wins over the tutor rule (10% / R25).
        $this->assertSame('24.00', (string) $transaction->platform_fee_total);
        $this->assertSame('176.00', (string) $transaction->tutor_amount);
    }

    public function test_changing_the_global_rule_does_not_alter_a_previously_recorded_transaction(): void
    {
        Http::fake(['*' => Http::response('VALID', 200)]);

        $order = $this->orderForNewCourse(price: 200);
        $payload = $this->signedItnPayload($order);
        $this->postJson('/api/payfast/itn', $payload)->assertOk();

        $transaction = FinancialTransaction::first();
        $this->assertSame('40.00', (string) $transaction->platform_fee_total);

        // Admin changes the global commission rate from 20% to 25% after the fact.
        FinancialRule::where('scope', FinancialRuleScope::Global)->first()->update(['percentage' => 25]);

        $transaction->refresh();
        $this->assertSame('40.00', (string) $transaction->platform_fee_total);
        $this->assertSame('160.00', (string) $transaction->tutor_amount);
    }
}
