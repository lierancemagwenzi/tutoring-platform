<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_raise_view_and_comment_on_their_own_ticket(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $store = $this->postJson('/api/support-tickets', [
            'subject' => 'Payment not going through', 'message' => 'Card declined every time.',
        ]);
        $store->assertCreated();
        $store->assertJsonPath('ticket.status', 'open');
        $store->assertJsonPath('ticket.user.id', $student->id);
        $ticketId = $store->json('ticket.id');

        $show = $this->getJson("/api/support-tickets/{$ticketId}");
        $show->assertOk();
        $show->assertJsonPath('ticket.subject', 'Payment not going through');

        $comment = $this->postJson("/api/support-tickets/{$ticketId}/comments", ['body' => 'Still happening.']);
        $comment->assertCreated();
        $comment->assertJsonPath('comment.author.is_admin', false);
    }

    public function test_a_tutor_can_raise_view_and_comment_on_their_own_ticket(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        Sanctum::actingAs($tutorUser);

        $store = $this->postJson('/api/support-tickets', [
            'subject' => 'Cannot upload qualification document', 'message' => 'Upload button does nothing.',
        ]);
        $store->assertCreated();
        $store->assertJsonPath('ticket.user.role', 'tutor');
        $ticketId = $store->json('ticket.id');

        $show = $this->getJson("/api/support-tickets/{$ticketId}");
        $show->assertOk();

        $comment = $this->postJson("/api/support-tickets/{$ticketId}/comments", ['body' => 'Tried a different browser too.']);
        $comment->assertCreated();
    }

    public function test_a_user_cannot_view_or_comment_on_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $ticket = SupportTicket::create(['user_id' => $owner->id, 'subject' => 'Q', 'message' => 'M', 'status' => 'open']);

        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/support-tickets/{$ticket->id}")->assertForbidden();
        $this->postJson("/api/support-tickets/{$ticket->id}/comments", ['body' => 'Hi'])->assertForbidden();
    }

    public function test_a_tutor_cannot_view_a_students_ticket_and_vice_versa(): void
    {
        $student = User::factory()->create();
        $studentTicket = SupportTicket::create(['user_id' => $student->id, 'subject' => 'Q', 'message' => 'M', 'status' => 'open']);

        $tutorUser = User::factory()->tutor()->create();
        Sanctum::actingAs($tutorUser);

        $this->getJson("/api/support-tickets/{$studentTicket->id}")->assertForbidden();
    }

    public function test_a_user_only_sees_their_own_tickets_in_the_list(): void
    {
        $student = User::factory()->create();
        SupportTicket::create(['user_id' => $student->id, 'subject' => 'Mine', 'message' => 'M', 'status' => 'open']);
        SupportTicket::create(['user_id' => User::factory()->create()->id, 'subject' => 'Not mine', 'message' => 'M', 'status' => 'open']);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/support-tickets');

        $response->assertOk();
        $response->assertJsonCount(1, 'tickets');
        $response->assertJsonPath('tickets.0.subject', 'Mine');
    }
}
