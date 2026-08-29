<?php

namespace App\Services\TutorWorkspace;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Bookings domain of the Tutor Workspace — pending booking requests.
 * Mirrors the exact query BookingRequestController::index already runs for
 * ?status=pending, so accepting/declining stays entirely on the existing
 * Booking Requests module; this only surfaces a preview of the same data.
 */
class BookingRequestsHubService
{
    /**
     * @var list<string>
     */
    private const WITH = ['student', 'service.subject', 'service.category', 'service.sessionFormat'];

    /**
     * @return Collection<int, Booking>
     */
    public function pending(TutorProfile $tutor, int $limit = 10): Collection
    {
        return $tutor->bookings()
            ->with(self::WITH)
            ->where('status', BookingStatus::Pending)
            ->latest()
            ->take($limit)
            ->get();
    }

    public function pendingCount(TutorProfile $tutor): int
    {
        return $tutor->bookings()->where('status', BookingStatus::Pending)->count();
    }
}
