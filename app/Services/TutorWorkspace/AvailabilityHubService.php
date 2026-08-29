<?php

namespace App\Services\TutorWorkspace;

use App\Models\AvailabilityDate;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The Availability domain of the Tutor Workspace. Reuses the existing
 * AvailabilityDate/AvailabilitySlot models as-is — availability here is
 * purely additive (a date either has configured slots or it doesn't), so
 * "unavailable" is derived as "no AvailabilityDate row for this day" rather
 * than a separate stored flag.
 */
class AvailabilityHubService
{
    public function todaysAvailability(TutorProfile $tutor): ?AvailabilityDate
    {
        return $tutor->availabilityDates()->with('slots')->whereDate('date', Carbon::today())->first();
    }

    /**
     * @return Collection<int, AvailabilityDate>
     */
    public function upcomingAvailability(TutorProfile $tutor, int $days = 14): Collection
    {
        return $tutor->availabilityDates()
            ->with('slots')
            ->whereBetween('date', [Carbon::tomorrow()->format('Y-m-d'), Carbon::today()->addDays($days)->format('Y-m-d')])
            ->orderBy('date')
            ->get();
    }

    /**
     * Dates within the window that have no configured availability at all.
     *
     * @return list<string>
     */
    public function unavailableDates(TutorProfile $tutor, int $days = 14): array
    {
        $configured = $tutor->availabilityDates()
            ->whereBetween('date', [Carbon::today()->format('Y-m-d'), Carbon::today()->addDays($days)->format('Y-m-d')])
            ->pluck('date')
            ->map(fn (Carbon $date) => $date->format('Y-m-d'))
            ->all();

        $unavailable = [];
        for ($i = 0; $i <= $days; $i++) {
            $date = Carbon::today()->addDays($i)->format('Y-m-d');
            if (! in_array($date, $configured, true)) {
                $unavailable[] = $date;
            }
        }

        return $unavailable;
    }
}
