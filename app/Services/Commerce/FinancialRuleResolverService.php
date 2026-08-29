<?php

namespace App\Services\Commerce;

use App\Enums\FinancialRuleScope;
use App\Enums\ProductType;
use App\Models\Booking;
use App\Models\FinancialRule;
use App\Models\OrderItem;
use App\Models\SelfPacedCourse;
use App\Models\TutorProfile;
use Illuminate\Support\Carbon;

/**
 * Resolves the most specific active FinancialRule applicable to a
 * purchased order line — Course-purchases check Course -> Tutor -> Global;
 * Service (tutoring booking) purchases check Service -> Tutor -> Global.
 * There is no separate "product type" tier: nothing in the schema needs a
 * bucket between Tutor and Global, since each product type only ever has
 * one scope above Tutor (Course for courses, Service for bookings).
 *
 * Effective-dated: more than one row can exist for the same scope/target
 * (see FinancialRuleManagementService — changing a rate creates a new row
 * rather than mutating the old one), so every lookup here picks the most
 * recent row whose effective_from has already passed, not just "the" row.
 * A future-dated row sits inert until its effective_from arrives.
 */
class FinancialRuleResolverService
{
    /**
     * The V1 platform defaults if no admin has configured anything yet.
     */
    private const DEFAULT_PERCENTAGE = 20.00;

    private const DEFAULT_FIXED_FEE = 0.00;

    public function resolveFor(OrderItem $item): FinancialRule
    {
        $product = $item->product;

        if ($item->product_type === ProductType::CourseOffering->value) {
            /** @var SelfPacedCourse $course */
            $course = $product;

            return $this->courseRule($course->id)
                ?? $this->tutorRule($course->tutor_profile_id)
                ?? $this->globalRule();
        }

        /** @var Booking $booking */
        $booking = $product;

        return $this->serviceRule($booking->service_id)
            ?? $this->tutorRule($booking->tutor_profile_id)
            ?? $this->globalRule();
    }

    /**
     * The rule that would apply to a new, override-less service/course for
     * this tutor — i.e. their general commission rate, shown to the tutor
     * for context (not tied to any specific purchase). Tutor-scope rule if
     * one exists, otherwise the global default.
     */
    public function applicableRuleForTutor(TutorProfile $tutor): FinancialRule
    {
        return $this->tutorRule($tutor->id) ?? $this->globalRule();
    }

    /**
     * The tutor profile a resolved OrderItem's purchase belongs to —
     * needed by the snapshot service alongside the resolved rule. Both
     * Booking and SelfPacedCourse expose the same tutorProfile() relation.
     */
    public function tutorProfileFor(OrderItem $item): TutorProfile
    {
        return $item->product->tutorProfile;
    }

    private function courseRule(int $courseId): ?FinancialRule
    {
        return $this->currentlyEffective(FinancialRuleScope::Course, ['self_paced_course_id' => $courseId]);
    }

    private function serviceRule(int $serviceId): ?FinancialRule
    {
        return $this->currentlyEffective(FinancialRuleScope::Service, ['service_id' => $serviceId]);
    }

    private function tutorRule(int $tutorProfileId): ?FinancialRule
    {
        return $this->currentlyEffective(FinancialRuleScope::Tutor, ['tutor_profile_id' => $tutorProfileId]);
    }

    /**
     * Always resolvable — created on first use with the V1 platform
     * defaults so no seeder step is required before the first payment.
     */
    public function globalRule(): FinancialRule
    {
        $rule = $this->currentlyEffective(FinancialRuleScope::Global);

        if ($rule) {
            return $rule;
        }

        return FinancialRule::create([
            'scope' => FinancialRuleScope::Global,
            'percentage' => self::DEFAULT_PERCENTAGE,
            'fixed_fee' => self::DEFAULT_FIXED_FEE,
            'currency' => 'ZAR',
            'is_active' => true,
            // Epoch, not now() — this represents "the baseline that has
            // always applied," so it must never accidentally outrank an
            // admin's legitimately back-dated rate change purely because
            // it happened to be materialized more recently in wall-clock
            // time (see FinancialRuleSchedulingTest for the regression
            // this fixes: auto-creating this row with effective_from=now()
            // could outrank an admin setting a rate effective yesterday).
            'effective_from' => Carbon::createFromTimestamp(0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function currentlyEffective(FinancialRuleScope $scope, array $target = []): ?FinancialRule
    {
        return FinancialRule::query()
            ->where('scope', $scope)
            ->where($target)
            ->where('is_active', true)
            ->where('effective_from', '<=', Carbon::now())
            ->orderByDesc('effective_from')
            // Tiebreaker for rows created within the same second (the
            // `timestamp` column has no sub-second precision) — the most
            // recently created row wins, matching "the last thing an admin
            // set" rather than depending on undefined row order.
            ->orderByDesc('id')
            ->first();
    }
}
