<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_recent_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::create(['name' => 'Mathematics']);
        AdminActivityLog::create([
            'actor_id' => $admin->id, 'action' => 'subject.status_changed', 'subject_type' => Subject::class,
            'subject_id' => $subject->id, 'description' => 'Subject "Mathematics" status changed from active to inactive.',
            'metadata' => ['from' => 'active', 'to' => 'inactive'],
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/activity-log');

        $response->assertOk();
        $response->assertJsonCount(1, 'logs');
        $response->assertJsonPath('logs.0.action', 'subject.status_changed');
        $response->assertJsonPath('logs.0.subject_type', 'Subject');
        $response->assertJsonPath('logs.0.actor.id', $admin->id);
        $response->assertJsonPath('logs.0.description', 'Subject "Mathematics" status changed from active to inactive.');
    }

    public function test_activity_log_is_ordered_most_recent_first(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::create(['name' => 'Mathematics']);
        $older = AdminActivityLog::create([
            'actor_id' => $admin->id, 'action' => 'subject.status_changed', 'subject_type' => Subject::class,
            'subject_id' => $subject->id, 'description' => 'Older entry.',
        ]);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        AdminActivityLog::create([
            'actor_id' => $admin->id, 'action' => 'subject.status_changed', 'subject_type' => Subject::class,
            'subject_id' => $subject->id, 'description' => 'Newer entry.',
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/activity-log');

        $response->assertOk();
        $response->assertJsonPath('logs.0.description', 'Newer entry.');
        $response->assertJsonPath('logs.1.description', 'Older entry.');
    }

    public function test_non_admin_cannot_view_the_activity_log(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        Sanctum::actingAs($tutorUser);

        $this->getJson('/api/admin/activity-log')->assertForbidden();
    }
}
