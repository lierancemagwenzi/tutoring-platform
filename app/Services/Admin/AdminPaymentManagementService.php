<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only admin visibility into payments — never exposes PayFast
 * secrets; only the transaction-level fields already stored on Payment
 * (reference, amount, status, provider) are surfaced.
 */
class AdminPaymentManagementService
{
    /**
     * @param  array{status?: string, provider?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Payment::query()->with([
            'order.student',
            'order.items.product' => fn ($morphTo) => $morphTo->morphWith([Booking::class => ['service']]),
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        return $query->latest()->paginate($perPage);
    }
}
