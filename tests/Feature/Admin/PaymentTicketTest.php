<?php

namespace Tests\Feature\Admin;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTicket;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTicketTest extends TestCase
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

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function transactionFor(TutorProfile $tutor): FinancialTransaction
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

    private function ticketFor(TutorProfile $tutor, FinancialTransaction $transaction, string $status = 'open'): PaymentTicket
    {
        return PaymentTicket::create([
            'financial_transaction_id' => $transaction->id, 'tutor_profile_id' => $tutor->id,
            'message' => 'Missing payment.', 'status' => $status,
        ]);
    }

    public function test_admin_can_list_all_tickets(): void
    {
        $this->admin();
        $tutor = $this->tutor();
        $this->ticketFor($tutor, $this->transactionFor($tutor));
        $this->ticketFor($tutor, $this->transactionFor($tutor));

        $response = $this->getJson('/api/admin/payment-tickets');

        $response->assertOk();
        $response->assertJsonCount(2, 'tickets');
    }

    public function test_admin_can_filter_tickets_by_status(): void
    {
        $this->admin();
        $tutor = $this->tutor();
        $this->ticketFor($tutor, $this->transactionFor($tutor), 'open');
        $this->ticketFor($tutor, $this->transactionFor($tutor), 'resolved');

        $response = $this->getJson('/api/admin/payment-tickets?status=resolved');

        $response->assertOk();
        $response->assertJsonCount(1, 'tickets');
        $response->assertJsonPath('tickets.0.status', 'resolved');
    }

    public function test_admin_can_view_ticket_detail(): void
    {
        $this->admin();
        $tutor = $this->tutor();
        $ticket = $this->ticketFor($tutor, $this->transactionFor($tutor));

        $response = $this->getJson("/api/admin/payment-tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertJsonPath('ticket.id', $ticket->id);
        $response->assertJsonPath('ticket.tutor.id', $tutor->id);
    }

    public function test_admin_can_update_ticket_status(): void
    {
        $admin = $this->admin();
        $tutor = $this->tutor();
        $ticket = $this->ticketFor($tutor, $this->transactionFor($tutor));

        $response = $this->patchJson("/api/admin/payment-tickets/{$ticket->id}/status", ['status' => 'in_review']);

        $response->assertOk();
        $response->assertJsonPath('ticket.status', 'in_review');
        $this->assertSame('in_review', $ticket->fresh()->status->value);
        $this->assertDatabaseHas('admin_activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'payment_ticket.status_updated',
            'subject_type' => PaymentTicket::class,
            'subject_id' => $ticket->id,
        ]);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $this->admin();
        $tutor = $this->tutor();
        $ticket = $this->ticketFor($tutor, $this->transactionFor($tutor));

        $response = $this->patchJson("/api/admin/payment-tickets/{$ticket->id}/status", ['status' => 'not-a-real-status']);

        $response->assertStatus(422);
        $this->assertSame('open', $ticket->fresh()->status->value);
    }

    public function test_admin_can_add_a_comment(): void
    {
        $admin = $this->admin();
        $tutor = $this->tutor();
        $ticket = $this->ticketFor($tutor, $this->transactionFor($tutor));

        $response = $this->postJson("/api/admin/payment-tickets/{$ticket->id}/comments", [
            'body' => 'Looking into this now.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('comment.author.is_admin', true);
        $this->assertDatabaseHas('payment_ticket_comments', [
            'payment_ticket_id' => $ticket->id, 'user_id' => $admin->id, 'body' => 'Looking into this now.',
        ]);
    }

    public function test_non_admin_cannot_access_admin_payment_ticket_routes(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $ticket = $this->ticketFor($tutor, $this->transactionFor($tutor));
        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/admin/payment-tickets');
        $response->assertForbidden();

        $response = $this->patchJson("/api/admin/payment-tickets/{$ticket->id}/status", ['status' => 'resolved']);
        $response->assertForbidden();
    }
}
