<?php

namespace App\Services\Admin;

use App\Models\TeachingSession;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only admin visibility into teaching sessions — surfaces meeting
 * provider/status so an admin can investigate scheduling or meeting-link
 * problems without touching availability or booking constraints.
 */
class AdminSessionManagementService
{
    /**
     * @param  array{status?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TeachingSession::query()
            ->with(['tutorProfile', 'service', 'bookings.student', 'sessionMeeting', 'sessionLessons.lesson']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('date')->paginate($perPage);
    }
}
