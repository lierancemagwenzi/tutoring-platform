<?php

namespace App\Services\Admin;

use App\Models\FinancialTransaction;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only admin visibility into the commission snapshots recorded by
 * CommissionSnapshotService — the platform's revenue/payout ledger.
 */
class FinancialTransactionReportingService
{
    /**
     * @param  array{tutor_profile_id?: int, product_type?: string, from?: string, to?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = FinancialTransaction::query()
            ->with(['tutorProfile', 'student', 'financialRule'])
            ->latest('created_at');

        if (! empty($filters['tutor_profile_id'])) {
            $query->where('tutor_profile_id', $filters['tutor_profile_id']);
        }

        if (! empty($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }
}
