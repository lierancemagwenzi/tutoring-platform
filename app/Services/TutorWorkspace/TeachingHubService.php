<?php

namespace App\Services\TutorWorkspace;

use App\Enums\SessionStatus;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The Teaching domain of the Tutor Workspace — today's and upcoming
 * scheduled sessions. Consumes TeachingSession exactly as
 * TeachingSessionController already does (same relations, same
 * TeachingSessionResource), so the workspace adds no new resource shape.
 */
class TeachingHubService
{
    /**
     * @var list<string>
     */
    private const WITH = [
        'service.subject',
        'service.category',
        'service.sessionFormat',
        'sessionMeeting',
        'bookings.student',
        'sessionLessons.lesson',
    ];

    public function todaysSessions(TutorProfile $tutor): Collection
    {
        return $this->scheduledSessions($tutor)
            ->whereDate('date', Carbon::today())
            ->orderBy('start_time')
            ->get();
    }

    public function upcomingSessions(TutorProfile $tutor, int $perPage = 10): LengthAwarePaginator
    {
        return $this->scheduledSessions($tutor)
            ->whereDate('date', '>', Carbon::today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($perPage);
    }

    public function upcomingSessionCount(TutorProfile $tutor): int
    {
        return $this->scheduledSessions($tutor)->whereDate('date', '>', Carbon::today())->count();
    }

    /**
     * A light, date-sorted preview of the next few days' sessions, for the
     * Calendar Preview widget.
     *
     * @return Collection<int, TeachingSession>
     */
    public function sessionsWithinDays(TutorProfile $tutor, int $days): Collection
    {
        return $this->scheduledSessions($tutor)
            ->whereBetween('date', [Carbon::today()->format('Y-m-d'), Carbon::today()->addDays($days)->format('Y-m-d')])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Today's sessions whose start time falls within the next $minutes —
     * feeds the Action Center's "Session starting soon" prompt.
     *
     * @return Collection<int, TeachingSession>
     */
    public function sessionsStartingSoon(TutorProfile $tutor, int $minutes = 60): Collection
    {
        $now = Carbon::now();
        $horizon = $now->copy()->addMinutes($minutes);

        return $this->todaysSessions($tutor)->filter(function (TeachingSession $session) use ($now, $horizon) {
            $startsAt = Carbon::parse($session->date->format('Y-m-d').' '.$session->start_time);

            return $startsAt->between($now, $horizon);
        })->values();
    }

    /**
     * The share of past sessions the tutor actually delivered — the one
     * "attendance" figure the Performance Snapshot surfaces, derived purely
     * from SessionStatus rather than a dedicated attendance tracker.
     */
    public function attendanceRate(TutorProfile $tutor): ?float
    {
        $past = $tutor->teachingSessions()->whereDate('date', '<', Carbon::today())->get();

        if ($past->isEmpty()) {
            return null;
        }

        return round($past->where('status', SessionStatus::Completed)->count() / $past->count() * 100, 2);
    }

    /**
     * @return HasMany<TeachingSession>
     */
    private function scheduledSessions(TutorProfile $tutor): HasMany
    {
        return $tutor->teachingSessions()->with(self::WITH)->where('status', SessionStatus::Scheduled);
    }
}
