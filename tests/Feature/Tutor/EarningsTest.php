<?php

namespace Tests\Feature\Tutor;

use App\Enums\FinancialRuleScope;
use App\Models\FinancialRule;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EarningsTest extends TestCase
{
    use RefreshDatabase;

    private function tutorWithProfile(): User
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        return $tutorUser;
    }

    /**
     * Creates a bare FinancialTransaction row directly — the full
     * ITN-driven creation path is already covered by PayFastItnTest; here
     * we only need transactions to exist to test the tutor-facing read
     * endpoints against them.
     */
    private function transactionFor(TutorProfile $tutor, float $tutorAmount): FinancialTransaction
    {
        $student = User::factory()->create();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Course', 'price' => 200, 'currency' => 'ZAR', 'status' => 'published', 'visibility' => 'public',
        ]);
        $order = Order::create([
            'student_id' => $student->id, 'order_number' => 'ORD-'.uniqid(),
            'status' => 'paid', 'currency' => 'ZAR', 'total_amount' => 200, 'discount_amount' => 0, 'final_amount' => 200,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_type' => 'course_offering', 'product_id' => $course->id,
            'quantity' => 1, 'unit_price' => 200, 'discount' => 0, 'total' => 200,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id, 'provider' => 'payfast', 'payment_reference' => 'PAY-'.uniqid(),
            'amount' => 200, 'currency' => 'ZAR', 'status' => 'successful', 'paid_at' => now(),
        ]);

        return FinancialTransaction::create([
            'payment_id' => $payment->id, 'order_id' => $order->id, 'order_item_id' => $item->id,
            'tutor_profile_id' => $tutor->id, 'student_id' => $student->id,
            'product_type' => 'course_offering', 'product_id' => $course->id,
            'gross_amount' => 200, 'currency' => 'ZAR', 'platform_fee_total' => 200 - $tutorAmount, 'tutor_amount' => $tutorAmount,
        ]);
    }

    public function test_tutor_sees_their_earnings_summary_and_applicable_rate(): void
    {
        $tutorUser = $this->tutorWithProfile();
        $this->transactionFor($tutorUser->tutorProfile, 160);
        $this->transactionFor($tutorUser->tutorProfile, 140);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/earnings');

        $response->assertOk();
        $response->assertJsonPath('all_time_earnings', '300.00');
        $response->assertJsonPath('transactions_count', 2);
        $response->assertJsonPath('applicable_rate.percentage', '20.00');
        $response->assertJsonPath('applicable_rate.scope', 'global');
    }

    public function test_tutor_scope_override_is_reflected_in_applicable_rate(): void
    {
        $tutorUser = $this->tutorWithProfile();
        FinancialRule::create(['scope' => FinancialRuleScope::Tutor, 'tutor_profile_id' => $tutorUser->tutorProfile->id, 'percentage' => 15]);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/earnings');

        $response->assertOk();
        $response->assertJsonPath('applicable_rate.percentage', '15.00');
        $response->assertJsonPath('applicable_rate.scope', 'tutor');
    }

    public function test_tutor_only_sees_their_own_transactions(): void
    {
        $tutorUser = $this->tutorWithProfile();
        $otherTutorUser = $this->tutorWithProfile();
        $this->transactionFor($tutorUser->tutorProfile, 160);
        $this->transactionFor($otherTutorUser->tutorProfile, 140);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/earnings/transactions');

        $response->assertOk();
        $response->assertJsonCount(1, 'transactions');
        $response->assertJsonPath('transactions.0.tutor_amount', '160.00');
    }

    public function test_earnings_transaction_resource_does_not_expose_platform_fee_breakdown(): void
    {
        $tutorUser = $this->tutorWithProfile();
        $this->transactionFor($tutorUser->tutorProfile, 160);

        Sanctum::actingAs($tutorUser);
        $response = $this->getJson('/api/tutor/earnings/transactions');

        $response->assertOk();
        $response->assertJsonMissingPath('transactions.0.platform_fee_total');
    }
}
