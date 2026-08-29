<?php

namespace Tests\Feature\Admin;

use App\Models\FinancialRule;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Commerce\FinancialRuleResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialRuleSchedulingTest extends TestCase
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

    public function test_updating_the_global_rule_without_a_date_applies_immediately(): void
    {
        $this->admin();
        $this->patchJson('/api/admin/financial-rules/global', ['percentage' => 15])->assertOk();

        $response = $this->getJson('/api/admin/financial-rules/global');

        $response->assertOk();
        $response->assertJsonPath('rule.percentage', '15.00');
        $response->assertJsonPath('rule.is_scheduled', false);
    }

    public function test_a_future_dated_global_rule_change_does_not_apply_yet(): void
    {
        $this->admin();
        // Establish the current rate first (at 20% default), then schedule
        // a change for next month.
        app(FinancialRuleResolverService::class)->globalRule();

        $this->patchJson('/api/admin/financial-rules/global', [
            'percentage' => 5,
            'effective_from' => now()->addMonth()->toDateString(),
        ])->assertOk();

        // The currently-effective rule must still be the old rate.
        $current = app(FinancialRuleResolverService::class)->globalRule();
        $this->assertSame('20.00', (string) $current->percentage);

        // But the future row was created and is queryable.
        $this->assertDatabaseHas('financial_rules', ['scope' => 'global', 'percentage' => 5]);
    }

    public function test_a_past_dated_global_rule_change_is_immediately_effective(): void
    {
        $this->admin();

        $this->patchJson('/api/admin/financial-rules/global', [
            'percentage' => 8,
            'effective_from' => now()->subDay()->toDateString(),
        ])->assertOk();

        $current = app(FinancialRuleResolverService::class)->globalRule();
        $this->assertSame('8.00', (string) $current->percentage);
    }

    public function test_scheduling_a_rate_change_does_not_alter_an_already_resolved_transaction(): void
    {
        $this->admin();
        $tutor = $this->tutor();

        // A booking's OrderItem resolves against whatever rate is currently
        // effective — simulate that by resolving now, then scheduling a
        // change, and confirming a fresh resolve for the SAME item still
        // returns the original rate (nothing retroactively changes it).
        $resolver = app(FinancialRuleResolverService::class);
        $before = $resolver->applicableRuleForTutor($tutor);
        $this->assertSame('20.00', (string) $before->percentage);

        $this->patchJson('/api/admin/financial-rules/global', [
            'percentage' => 50,
            'effective_from' => now()->addWeek()->toDateString(),
        ])->assertOk();

        $after = $resolver->applicableRuleForTutor($tutor);
        $this->assertSame('20.00', (string) $after->percentage);
    }

    public function test_updating_a_tutor_override_creates_a_new_row_and_keeps_the_old_one(): void
    {
        $this->admin();
        $tutor = $this->tutor();

        $original = FinancialRule::create([
            'scope' => 'tutor', 'tutor_profile_id' => $tutor->id, 'percentage' => 15, 'is_active' => true,
        ]);

        $response = $this->patchJson("/api/admin/financial-rules/{$original->id}", ['percentage' => 12]);

        $response->assertOk();
        $newId = $response->json('rule.id');
        $this->assertNotSame($original->id, $newId);
        // The old row is untouched — still exists with its original rate.
        $this->assertDatabaseHas('financial_rules', ['id' => $original->id, 'percentage' => 15]);
        $this->assertDatabaseHas('financial_rules', ['id' => $newId, 'percentage' => 12]);

        $resolver = app(FinancialRuleResolverService::class);
        $this->assertSame('12.00', (string) $resolver->applicableRuleForTutor($tutor)->percentage);
    }
}
