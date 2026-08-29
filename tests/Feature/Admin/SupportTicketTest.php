<?php

namespace Tests\Feature\Admin;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function ticketFor(User $user, string $status = 'open'): SupportTicket
    {
        return SupportTicket::create([
            'user_id' => $user->id, 'subject' => 'Can\'t access my dashboard', 'message' => 'Getting a blank page.', 'status' => $status,
        ]);
    }

    public function test_admin_can_list_all_tickets(): void
    {
        $this->admin();
        $this->ticketFor(User::factory()->create());
        $this->ticketFor(User::factory()->tutor()->create());

        $response = $this->getJson('/api/admin/support-tickets');

        $response->assertOk();
        $response->assertJsonCount(2, 'tickets');
    }

    public function test_admin_can_filter_tickets_by_status(): void
    {
        $this->admin();
        $this->ticketFor(User::factory()->create(), 'open');
        $this->ticketFor(User::factory()->create(), 'resolved');

        $response = $this->getJson('/api/admin/support-tickets?status=resolved');

        $response->assertOk();
        $response->assertJsonCount(1, 'tickets');
        $response->assertJsonPath('tickets.0.status', 'resolved');
    }

    public function test_admin_can_view_ticket_detail(): void
    {
        $this->admin();
        $student = User::factory()->create();
        $ticket = $this->ticketFor($student);

        $response = $this->getJson("/api/admin/support-tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertJsonPath('ticket.id', $ticket->id);
        $response->assertJsonPath('ticket.user.id', $student->id);
        $response->assertJsonPath('ticket.user.role', 'student');
    }

    public function test_admin_can_update_ticket_status(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticketFor(User::factory()->create());

        $response = $this->patchJson("/api/admin/support-tickets/{$ticket->id}/status", ['status' => 'in_review']);

        $response->assertOk();
        $response->assertJsonPath('ticket.status', 'in_review');
        $this->assertSame('in_review', $ticket->fresh()->status->value);
        $this->assertDatabaseHas('admin_activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'support_ticket.status_updated',
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
        ]);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $this->admin();
        $ticket = $this->ticketFor(User::factory()->create());

        $response = $this->patchJson("/api/admin/support-tickets/{$ticket->id}/status", ['status' => 'not-a-real-status']);

        $response->assertStatus(422);
        $this->assertSame('open', $ticket->fresh()->status->value);
    }

    public function test_admin_can_add_a_comment(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticketFor(User::factory()->create());

        $response = $this->postJson("/api/admin/support-tickets/{$ticket->id}/comments", [
            'body' => 'Can you share a screenshot?',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('comment.author.is_admin', true);
        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id, 'user_id' => $admin->id, 'body' => 'Can you share a screenshot?',
        ]);
    }

    public function test_non_admin_cannot_access_admin_support_ticket_routes(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $ticket = $this->ticketFor($tutorUser);
        Sanctum::actingAs($tutorUser);

        $this->getJson('/api/admin/support-tickets')->assertForbidden();
        $this->patchJson("/api/admin/support-tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertForbidden();
    }
}
