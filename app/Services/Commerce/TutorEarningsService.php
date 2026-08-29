<?php

namespace App\Services\Commerce;

use App\Enums\PayoutStatus;
use App\Models\FinancialTransaction;
use App\Models\TutorProfile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * A tutor's read-only view of their own earnings — the applicable
 * commission rate is shown for transparency, never editable here; only
 * an admin can change a FinancialRule (see Admin\FinancialRuleController).
 */
class TutorEarningsService
{
    public function __construct(private readonly FinancialRuleResolverService $resolver) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(TutorProfile $tutor): array
    {
        $allTime = FinancialTransaction::where('tutor_profile_id', $tutor->id)->sum('tutor_amount');
        $thisMonth = FinancialTransaction::where('tutor_profile_id', $tutor->id)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('tutor_amount');
        // Eligible/Processing/On Hold are all still "on their way to being
        // paid" from the tutor's perspective — only Paid and Adjusted
        // (needs manual reconciliation, see RefundService) fall outside
        // this bucket.
        $pending = FinancialTransaction::where('tutor_profile_id', $tutor->id)
            ->whereIn('payout_status', [PayoutStatus::Pending, PayoutStatus::Eligible, PayoutStatus::Processing, PayoutStatus::OnHold])
            ->sum('tutor_amount');
        $paid = FinancialTransaction::where('tutor_profile_id', $tutor->id)
            ->where('payout_status', PayoutStatus::Paid)
            ->sum('tutor_amount');
        $adjusted = FinancialTransaction::where('tutor_profile_id', $tutor->id)
            ->where('payout_status', PayoutStatus::Adjusted)
            ->sum('tutor_amount');

        $rule = $this->resolver->applicableRuleForTutor($tutor);

        return [
            // number_format rather than a raw float: sum() aggregates aren't
            // Eloquent-cast attributes, so without this, fractional totals
            // (e.g. 62.35) serialize with binary floating-point noise.
            'all_time_earnings' => number_format((float) $allTime, 2, '.', ''),
            'this_month_earnings' => number_format((float) $thisMonth, 2, '.', ''),
            'pending_payout_total' => number_format((float) $pending, 2, '.', ''),
            'paid_total' => number_format((float) $paid, 2, '.', ''),
            'adjusted_total' => number_format((float) $adjusted, 2, '.', ''),
            'transactions_count' => FinancialTransaction::where('tutor_profile_id', $tutor->id)->count(),
            'applicable_rate' => [
                'percentage' => $rule->percentage,
                'fixed_fee' => $rule->fixed_fee,
                'scope' => $rule->scope->value,
            ],
        ];
    }

    public function transactions(TutorProfile $tutor, int $perPage = 15): LengthAwarePaginator
    {
        return FinancialTransaction::where('tutor_profile_id', $tutor->id)
            ->latest('created_at')
            ->paginate($perPage);
    }
}
