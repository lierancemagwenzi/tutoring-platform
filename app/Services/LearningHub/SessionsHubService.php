<?php

namespace App\Services\LearningHub;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Models\TeachingSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The Sessions domain of the Learning Hub — today's and upcoming Scheduled
 * sessions for a student. Queries TeachingSession directly (via the
 * Booking<->TeachingSession pivot) rather than Booking, since a booking can
 * now have several sessions on different dates — "today's sessions" is
 * genuinely a question about sessions, not bookings.
 */
class SessionsHubService
{
    /**
     * @var list<string>
     */
    private const WITH = [
        'tutorProfile', 'service.subject', 'service.category', 'service.sessionFormat',
        'sessionMeeting', 'sessionLessons.lesson', 'bookings',
    ];

    /**
     * The student's Scheduled sessions falling on today's date.
     */
    public function todaysSessions(User $student): Collection
    {
        return $this->studentSessions($student)
            ->whereDate('date', Carbon::today())
            ->orderBy('start_time')
            ->get();
    }

    /**
     * The student's Scheduled sessions falling after today.
     */
    public function upcomingSessions(User $student, int $perPage = 10): LengthAwarePaginator
    {
        return $this->studentSessions($student)
            ->whereDate('date', '>', Carbon::today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($perPage);
    }

    /**
     * How many Scheduled sessions fall on today's date — used by the
     * greeting/quick-stats, computed once rather than re-deriving from a
     * loaded collection with different pagination elsewhere.
     */
    public function todaysSessionCount(User $student): int
    {
        return $this->studentSessions($student)->whereDate('date', Carbon::today())->count();
    }

    /**
     * How many Scheduled sessions fall after today — feeds the "Upcoming
     * Sessions" quick stat without loading the full paginated collection.
     */
    public function upcomingSessionCount(User $student): int
    {
        return $this->studentSessions($student)->whereDate('date', '>', Carbon::today())->count();
    }

    /**
     * A light, date-sorted preview of the next few days' sessions, for the
     * Calendar Preview widget. The full calendar is a later phase.
     *
     * @return Collection<int, TeachingSession>
     */
    public function sessionsWithinDays(User $student, int $days): Collection
    {
        return $this->studentSessions($student)
            ->whereBetween('date', [Carbon::today()->format('Y-m-d'), Carbon::today()->addDays($days)->format('Y-m-d')])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @return Builder<TeachingSession>
     */
    private function studentSessions(User $student): Builder
    {
        return TeachingSession::query()
            ->with(self::WITH)
            ->where('status', SessionStatus::Scheduled)
            ->whereHas('bookings', fn ($query) => $query->where('student_id', $student->id)->where('status', BookingStatus::Confirmed));
    }
}
