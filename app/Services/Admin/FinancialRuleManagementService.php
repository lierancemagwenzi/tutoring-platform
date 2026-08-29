<?php

namespace App\Services\Admin;

use App\Enums\FinancialRuleScope;
use App\Models\FinancialRule;
use App\Models\User;
use App\Services\Commerce\FinancialRuleResolverService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Admin CRUD over commission rules. Tutor/service/course overrides can be
 * created, updated, and deactivated (soft toggle — falls through to the
 * next tier naturally, no resolver change needed); the Global rule is
 * always resolvable via FinancialRuleResolverService::globalRule().
 *
 * Effective-dated: "updating" a rule never mutates the existing row —
 * updateGlobal()/updateOverride() both create a NEW row with its own
 * effective_from (defaulting to now, i.e. "apply immediately" when the
 * admin doesn't pick a future date), leaving prior rows intact as history.
 * FinancialRuleResolverService picks whichever row's effective_from has
 * actually passed, so scheduling a future rate change here never affects
 * a booking made before that date arrives. Every mutation is audit-logged
 * since this is money.
 */
class FinancialRuleManagementService
{
    public function __construct(
        private readonly FinancialRuleResolverService $resolver,
        private readonly AdminActivityLogger $logger,
    ) {}

    /**
     * @param  array{scope?: string}  $filters
     */
    public function listOverrides(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = FinancialRule::query()
            ->where('scope', '!=', FinancialRuleScope::Global)
            ->with(['tutorProfile.user', 'service', 'selfPacedCourse']);

        if (! empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function globalRule(): FinancialRule
    {
        return $this->resolver->globalRule();
    }

    /**
     * @param  array{percentage: float, fixed_fee?: float, provider_fee_percentage?: ?float, provider_fee_fixed?: ?float, effective_from?: ?string}  $data
     */
    public function updateGlobal(array $data, User $actor): FinancialRule
    {
        $current = $this->globalRule();
        $effectiveFrom = isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : Carbon::now();

        $rule = FinancialRule::create([
            'scope' => FinancialRuleScope::Global,
            'percentage' => $data['percentage'],
            'fixed_fee' => $data['fixed_fee'] ?? 0,
            'provider_fee_percentage' => $data['provider_fee_percentage'] ?? null,
            'provider_fee_fixed' => $data['provider_fee_fixed'] ?? null,
            'currency' => $current->currency,
            'is_active' => true,
            'created_by' => $actor->id,
            'effective_from' => $effectiveFrom,
        ]);

        $this->logger->log(
            $actor,
            'financial_rule.updated',
            $rule,
            $effectiveFrom->isFuture()
                ? "Scheduled a new global commission rule effective {$effectiveFrom->toDateString()}."
                : 'Updated the global commission rule, effective immediately.',
            ['before' => $current->only(['percentage', 'fixed_fee', 'provider_fee_percentage', 'provider_fee_fixed']), 'after' => $rule->only(['percentage', 'fixed_fee', 'provider_fee_percentage', 'provider_fee_fixed']), 'effective_from' => $effectiveFrom->toIso8601String()],
        );

        return $rule;
    }

    /**
     * @param  array{scope: string, tutor_profile_id?: int, service_id?: int, self_paced_course_id?: int, percentage: float, fixed_fee?: float, provider_fee_percentage?: ?float, provider_fee_fixed?: ?float, effective_from?: ?string}  $data
     */
    public function createOverride(array $data, User $actor): FinancialRule
    {
        $rule = FinancialRule::create([
            'scope' => $data['scope'],
            'tutor_profile_id' => $data['tutor_profile_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'self_paced_course_id' => $data['self_paced_course_id'] ?? null,
            'percentage' => $data['percentage'],
            'fixed_fee' => $data['fixed_fee'] ?? 0,
            'provider_fee_percentage' => $data['provider_fee_percentage'] ?? null,
            'provider_fee_fixed' => $data['provider_fee_fixed'] ?? null,
            'currency' => 'ZAR',
            'is_active' => true,
            'created_by' => $actor->id,
            'effective_from' => isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : Carbon::now(),
        ]);

        $this->logger->log(
            $actor,
            'financial_rule.created',
            $rule,
            "Created a {$rule->scope->value}-scope commission rule.",
            ['scope' => $rule->scope->value, 'percentage' => (float) $rule->percentage, 'fixed_fee' => (float) $rule->fixed_fee],
        );

        return $rule;
    }

    /**
     * Creates a new row targeting the same scope/tutor/service/course as
     * $rule rather than mutating it — see class docblock. Scope and target
     * are immutable after creation (matches UpdateFinancialRuleRequest),
     * only percentage/fees/effective_from carry over from $data.
     *
     * @param  array{percentage: float, fixed_fee?: float, provider_fee_percentage?: ?float, provider_fee_fixed?: ?float, effective_from?: ?string}  $data
     */
    public function updateOverride(FinancialRule $rule, array $data, User $actor): FinancialRule
    {
        $effectiveFrom = isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : Carbon::now();

        $new = FinancialRule::create([
            'scope' => $rule->scope,
            'tutor_profile_id' => $rule->tutor_profile_id,
            'service_id' => $rule->service_id,
            'self_paced_course_id' => $rule->self_paced_course_id,
            'percentage' => $data['percentage'],
            'fixed_fee' => $data['fixed_fee'] ?? 0,
            'provider_fee_percentage' => $data['provider_fee_percentage'] ?? null,
            'provider_fee_fixed' => $data['provider_fee_fixed'] ?? null,
            'currency' => $rule->currency,
            'is_active' => true,
            'created_by' => $actor->id,
            'effective_from' => $effectiveFrom,
        ]);

        $this->logger->log(
            $actor,
            'financial_rule.updated',
            $new,
            $effectiveFrom->isFuture()
                ? "Scheduled a new {$rule->scope->value}-scope commission rule effective {$effectiveFrom->toDateString()}."
                : "Updated a {$rule->scope->value}-scope commission rule, effective immediately.",
            ['before' => $rule->only(['percentage', 'fixed_fee', 'provider_fee_percentage', 'provider_fee_fixed']), 'after' => $new->only(['percentage', 'fixed_fee', 'provider_fee_percentage', 'provider_fee_fixed']), 'effective_from' => $effectiveFrom->toIso8601String()],
        );

        return $new;
    }

    public function deactivateOverride(FinancialRule $rule, User $actor): FinancialRule
    {
        $rule->update(['is_active' => false]);

        $this->logger->log(
            $actor,
            'financial_rule.deactivated',
            $rule,
            "Deactivated a {$rule->scope->value}-scope commission rule.",
        );

        return $rule;
    }

    public function activateOverride(FinancialRule $rule, User $actor): FinancialRule
    {
        $rule->update(['is_active' => true]);

        $this->logger->log(
            $actor,
            'financial_rule.activated',
            $rule,
            "Reactivated a {$rule->scope->value}-scope commission rule.",
        );

        return $rule;
    }
}
