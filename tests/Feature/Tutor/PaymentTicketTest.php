<?php

namespace Tests\Feature\Tutor;

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

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
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

    public function test_tutor_can_raise_a_ticket_on_their_own_transaction(): void
    {
        $tutor = $this->tutor();
        $transaction = $this->transactionFor($tutor);
        Sanctum::actingAs($tutor->user);

        $response = $this->postJson("/api/tutor/financial-transactions/{$transaction->id}/tickets", [
            'message' => "I completed this but haven't received payment.",
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ticket.status', 'open');
        $response->assertJsonPath('ticket.transaction.id', $transaction->id);
        $this->assertDatabaseHas('payment_tickets', [
            'financial_transaction_id' => $transaction->id,
            'tutor_profile_id' => $tutor->id,
            'status' => 'open',
        ]);
    }

    public function test_tutor_cannot_raise_a_ticket_on_another_tutors_transaction(): void
    {
        $owner = $this->tutor();
        $transaction = $this->transactionFor($owner);
        $otherTutor = $this->tutor();
        Sanctum::actingAs($otherTutor->user);

        $response = $this->postJson("/api/tutor/financial-transactions/{$transaction->id}/tickets", [
            'message' => 'Not mine.',
        ]);

        $response->assertForbidden();
    }

    public function test_tutor_can_list_their_own_tickets(): void
    {
        $tutor = $this->tutor();
        $transaction = $this->transactionFor($tutor);
        PaymentTicket::create([
            'financial_transaction_id' => $transaction->id, 'tutor_profile_id' => $tutor->id,
            'message' => 'Missing payment.', 'status' => 'open',
        ]);
        Sanctum::actingAs($tutor->user);

        $response = $this->getJson('/api/tutor/payment-tickets');

        $response->assertOk();
        $response->assertJsonCount(1, 'tickets');
    }

    public function test_tutor_can_view_their_own_ticket_with_comments(): void
    {
        $tutor = $this->tutor();
        $transaction = $this->transactionFor($tutor);
        $ticket = PaymentTicket::create([
            'financial_transaction_id' => $transaction->id, 'tutor_profile_id' => $tutor->id,
            'message' => 'Missing payment.', 'status' => 'open',
        ]);
        $ticket->comments()->create(['user_id' => $tutor->user_id, 'body' => 'Any update?']);
        Sanctum::actingAs($tutor->user);

        $response = $this->getJson("/api/tutor/payment-tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'comments');
        $response->assertJsonPath('comments.0.body', 'Any update?');
    }

    public function test_tutor_cannot_view_another_tutors_ticket(): void
    {
        $owner = $this->tutor();
        $transaction = $this->transactionFor($owner);
        $ticket = PaymentTicket::create([
            'financial_transaction_id' => $transaction->id, 'tutor_profile_id' => $owner->id,
            'message' => 'Missing payment.', 'status' => 'open',
        ]);
        $otherTutor = $this->tutor();
        Sanctum::actingAs($otherTutor->user);

        $response = $this->getJson("/api/tutor/payment-tickets/{$ticket->id}");

        $response->assertForbidden();
    }

    public function test_tutor_can_add_a_comment_to_their_own_ticket(): void
    {
        $tutor = $this->tutor();
        $transaction = $this->transactionFor($tutor);
        $ticket = PaymentTicket::create([
            'financial_transaction_id' => $transaction->id, 'tutor_profile_id' => $tutor->id,
            'message' => 'Missing payment.', 'status' => 'open',
        ]);
        Sanctum::actingAs($tutor->user);

        $response = $this->postJson("/api/tutor/payment-tickets/{$ticket->id}/comments", [
            'body' => 'Following up on this.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('comment.body', 'Following up on this.');
        $response->assertJsonPath('comment.author.is_admin', false);
        $this->assertDatabaseHas('payment_ticket_comments', [
            'payment_ticket_id' => $ticket->id, 'user_id' => $tutor->user_id, 'body' => 'Following up on this.',
        ]);
    }
}
