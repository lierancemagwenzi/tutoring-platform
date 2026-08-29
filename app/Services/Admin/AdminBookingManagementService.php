<?php

namespace App\Services\Admin;

use App\Enums\SessionStatus;
use App\Models\Booking;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only admin visibility into bookings. Session-related counts
 * (scheduled/completed) are always computed from the Sessions table at
 * read time, never stored as counters on the Booking row.
 */
class AdminBookingManagementService
{
    /**
     * @param  array{status?: string, search?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with(['student', 'tutorProfile', 'service.subject', 'order.latestPayment'])
            ->withCount([
                'teachingSessions as scheduled_sessions_count' => fn ($q) => $q->where('status', SessionStatus::Scheduled),
                'teachingSessions as completed_sessions_count' => fn ($q) => $q->where('status', SessionStatus::Completed),
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return $query->latest()->paginate($perPage);
    }
}
