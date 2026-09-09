<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\SupportTicket;
use App\Models\TutorProfile;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_notifications_with_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new UserNotification('booking.created', 'New booking request', 'Someone booked you.', '/tutor/booking-requests'));
        $user->notify(new UserNotification('booking.message', 'New message', 'Hello there.', '/tutor/bookings/1'));
        $user->notifications()->latest()->first()->markAsRead();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonCount(2, 'notifications');
        $response->assertJsonPath('unread_count', 1);
    }

    public function test_user_only_sees_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->notify(new UserNotification('booking.created', 'New booking request', 'Someone booked you.', null));
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonCount(0, 'notifications');
    }

    public function test_marking_a_notification_read_updates_read_at(): void
    {
        $user = User::factory()->create();
        $user->notify(new UserNotification('booking.created', 'New booking request', 'Someone booked you.', null));
        $notification = $user->notifications()->first();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new UserNotification('booking.created', 'New booking request', 'Someone booked you.', null));
        $notification = $owner->notifications()->first();
        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(404);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new UserNotification('booking.created', 'New booking request', 'Someone booked you.', null));
        $user->notify(new UserNotification('booking.message', 'New message', 'Hello there.', null));
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notifications/read-all');

        $response->assertOk();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_support_ticket_status_update_notifies_the_raiser(): void
    {
        $admin = User::factory()->admin()->create();
        $raiser = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $raiser->id, 'subject' => 'Help', 'message' => 'Something broke.', 'status' => 'open',
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/support-tickets/{$ticket->id}/status", ['status' => 'in_review'])->assertOk();

        $this->assertSame(1, $raiser->fresh()->notifications()->count());
        $this->assertSame('support_ticket.status_updated', $raiser->fresh()->notifications()->first()->data['type']);
    }

    public function test_tutor_approval_notifies_the_tutor(): void
    {
        $admin = User::factory()->admin()->create();
        $tutorUser = User::factory()->tutor()->create(['status' => UserStatus::Pending]);
        $profile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Pending Tutor']);
        $profile->bankAccount()->create([
            'bank_name' => 'Test Bank', 'account_holder_name' => 'Pending Tutor',
            'account_number' => '123456789', 'branch_code' => '000000', 'account_type' => 'savings',
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/tutors/{$tutorUser->id}/approve")->assertOk();

        $this->assertSame(1, $tutorUser->fresh()->notifications()->count());
        $this->assertSame('tutor.approved', $tutorUser->fresh()->notifications()->first()->data['type']);
    }
}
