<?php

namespace App\Services\Admin;

use App\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only admin visibility into tutoring services (the Tutor-Led
 * marketplace offerings) — list-level only; a service's own detail is
 * already fully exposed via its existing fields, so no separate detail
 * aggregation is needed beyond the booking count.
 */
class AdminTutoringServiceManagementService
{
    /**
     * @param  array{visibility?: string, search?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Service::query()
            ->with(['tutorProfile', 'subject'])
            ->withCount('bookings');

        if (! empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        return $query->latest()->paginate($perPage);
    }
}
