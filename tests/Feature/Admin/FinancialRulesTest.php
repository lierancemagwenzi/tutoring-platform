<?php

namespace Tests\Feature\Admin;

use App\Enums\FinancialRuleScope;
use App\Models\FinancialRule;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Commerce\FinancialRuleResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialRulesTest extends TestCase
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

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    public function test_global_rule_is_auto_created_with_v1_defaults(): void
    {
        $this->admin();

        $response = $this->getJson('/api/admin/financial-rules/global');

        $response->assertOk();
        $response->assertJsonPath('rule.scope', 'global');
        $response->assertJsonPath('rule.percentage', '20.00');
        $response->assertJsonPath('rule.fixed_fee', '0.00');
    }

    public function test_admin_can_update_the_global_rule(): void
    {
        $this->admin();

        $response = $this->patchJson('/api/admin/financial-rules/global', ['percentage' => 15, 'fixed_fee' => 10]);

        $response->assertOk();
        $response->assertJsonPath('rule.percentage', '15.00');
        $response->assertJsonPath('rule.fixed_fee', '10.00');
        $this->assertDatabaseHas('financial_rules', ['scope' => 'global', 'percentage' => 15]);
    }

    public function test_admin_can_create_a_tutor_scope_override(): void
    {
        $this->admin();
        $tutor = $this->tutor();

        $response = $this->postJson('/api/admin/financial-rules', [
            'scope' => 'tutor',
            'tutor_profile_id' => $tutor->id,
            'percentage' => 10,
            'fixed_fee' => 25,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('rule.scope', 'tutor');
        $response->assertJsonPath('rule.target.id', $tutor->id);
        // The immediate create response must reflect the actual stored
        // currency, not the model's in-memory default before a refresh.
        $response->assertJsonPath('rule.currency', 'ZAR');
    }

    public function test_cannot_create_a_second_active_rule_for_the_same_tutor(): void
    {
        $this->admin();
        $tutor = $this->tutor();
        FinancialRule::create(['scope' => FinancialRuleScope::Tutor, 'tutor_profile_id' => $tutor->id, 'percentage' => 10]);

        $response = $this->postJson('/api/admin/financial-rules', [
            'scope' => 'tutor',
            'tutor_profile_id' => $tutor->id,
            'percentage' => 12,
        ]);

        $response->assertStatus(422);
    }

    public function test_scope_and_target_mismatch_is_rejected(): void
    {
        $this->admin();
        $tutor = $this->tutor();

        $response = $this->postJson('/api/admin/financial-rules', [
            'scope' => 'tutor',
            'service_id' => 1,
            'tutor_profile_id' => $tutor->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_deactivate_and_reactivate_an_override(): void
    {
        $this->admin();
        $tutor = $this->tutor();
        $rule = FinancialRule::create(['scope' => FinancialRuleScope::Tutor, 'tutor_profile_id' => $tutor->id, 'percentage' => 10]);

        $deactivate = $this->postJson("/api/admin/financial-rules/{$rule->id}/deactivate");
        $deactivate->assertOk();
        $deactivate->assertJsonPath('rule.is_active', false);

        $activate = $this->postJson("/api/admin/financial-rules/{$rule->id}/activate");
        $activate->assertOk();
        $activate->assertJsonPath('rule.is_active', true);
    }

    public function test_deactivated_override_falls_through_to_the_global_rule(): void
    {
        $this->admin();
        $tutor = $this->tutor();
        $rule = FinancialRule::create(['scope' => FinancialRuleScope::Tutor, 'tutor_profile_id' => $tutor->id, 'percentage' => 10]);
        $rule->update(['is_active' => false]);

        $resolved = app(FinancialRuleResolverService::class)->applicableRuleForTutor($tutor);

        $this->assertSame('global', $resolved->scope->value);
    }

    public function test_non_admin_cannot_access_financial_rules(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/admin/financial-rules/global');

        $response->assertForbidden();
    }

    public function test_admin_can_view_financial_transactions_list(): void
    {
        $this->admin();

        $response = $this->getJson('/api/admin/financial-transactions');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 0);
    }
}
