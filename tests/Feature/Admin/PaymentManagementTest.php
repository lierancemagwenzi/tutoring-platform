<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_payments_filtered_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $order = Order::create([
            'student_id' => $student->id,
            'order_number' => 'ORD-TEST-1',
            'status' => 'paid',
            'currency' => 'ZAR',
            'total_amount' => 100,
            'discount_amount' => 0,
            'final_amount' => 100,
        ]);
        $order->payments()->create([
            'provider' => 'payfast',
            'payment_reference' => 'PAY-TEST-1',
            'amount' => 100,
            'currency' => 'ZAR',
            'status' => 'successful',
            'paid_at' => now(),
        ]);
        $order->payments()->create([
            'provider' => 'payfast',
            'payment_reference' => 'PAY-TEST-2',
            'amount' => 100,
            'currency' => 'ZAR',
            'status' => 'failed',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/payments?status=successful');

        $response->assertOk();
        $response->assertJsonCount(1, 'payments');
        $response->assertJsonPath('payments.0.payment_reference', 'PAY-TEST-1');
        $response->assertJsonPath('payments.0.student', trim("{$student->first_name} {$student->last_name}"));
    }

    public function test_payment_response_never_exposes_provider_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $order = Order::create([
            'student_id' => $student->id,
            'order_number' => 'ORD-TEST-2',
            'status' => 'paid',
            'currency' => 'ZAR',
            'total_amount' => 100,
            'discount_amount' => 0,
            'final_amount' => 100,
        ]);
        $order->payments()->create([
            'provider' => 'payfast',
            'payment_reference' => 'PAY-TEST-3',
            'amount' => 100,
            'currency' => 'ZAR',
            'status' => 'successful',
            'provider_response' => ['merchant_id' => 'secret-merchant-id', 'signature' => 'secret-signature'],
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/payments');

        $response->assertOk();
        $this->assertStringNotContainsString('secret-merchant-id', $response->getContent());
        $this->assertStringNotContainsString('secret-signature', $response->getContent());
    }
}
