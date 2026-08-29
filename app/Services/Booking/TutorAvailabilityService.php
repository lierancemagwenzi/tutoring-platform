<?php

namespace App\Services\Booking;

use App\Enums\SessionStatus;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use Illuminate\Support\Carbon;

/**
 * Computes a tutor's real, current availability for manual session
 * scheduling: configured availability windows minus whatever's already
 * scheduled. Distinct from BookingAvailabilityService, which computes
 * student-facing checkout availability from Bookings, not Sessions — the
 * two serve different UIs (student checkout vs. tutor scheduling) and are
 * deliberately kept independent.
 */
class TutorAvailabilityService
{
    /**
     * The tutor's configured availability windows for a date, with any time
     * already covered by a non-cancelled TeachingSession subtracted out.
     * The configured AvailabilityDate/AvailabilitySlot rows are never
     * modified — this is always computed fresh.
     *
     * @return list<array{start_time: string, end_time: string}>
     */
    public function availableWindowsFor(TutorProfile $tutor, string $date): array
    {
        $availabilityDate = $tutor->availabilityDates()
            ->where('date', $date)
            ->with('slots')
            ->first();

        if (! $availabilityDate) {
            return [];
        }

        $busyWindows = $this->busyWindowsFor($tutor, $date);

        $windows = [];

        foreach ($availabilityDate->slots as $slot) {
            $windows = array_merge($windows, $this->subtractBusy($slot->start_time, $slot->end_time, $busyWindows));
        }

        return $windows;
    }

    /**
     * Whether the requested window fits entirely within a single computed
     * available window (it must not straddle a gap left by another session).
     */
    public function isWindowAvailable(TutorProfile $tutor, string $date, string $startTime, string $endTime): bool
    {
        $requestedStart = Carbon::parse($startTime);
        $requestedEnd = Carbon::parse($endTime);

        foreach ($this->availableWindowsFor($tutor, $date) as $window) {
            if ($requestedStart->gte(Carbon::parse($window['start_time'])) && $requestedEnd->lte(Carbon::parse($window['end_time']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * True double-booking check: does the requested window overlap any
     * OTHER non-cancelled session for this tutor, regardless of service or
     * booking. Callers should only invoke this once no exact-match session
     * to attach to was found (see SessionSchedulingService) — otherwise a
     * legitimate group-class occurrence would look like a conflict with
     * itself.
     */
    public function hasOverlap(TutorProfile $tutor, string $date, string $startTime, string $endTime, ?int $excludeSessionId = null): bool
    {
        $requestedStart = Carbon::parse($startTime);
        $requestedEnd = Carbon::parse($endTime);

        return TeachingSession::query()
            ->where('tutor_profile_id', $tutor->id)
            ->where('date', $date)
            ->where('status', '!=', SessionStatus::Cancelled)
            ->when($excludeSessionId, fn ($query) => $query->where('id', '!=', $excludeSessionId))
            ->get(['start_time', 'end_time'])
            ->contains(fn ($session) => $requestedStart->lt(Carbon::parse($session->end_time))
                && $requestedEnd->gt(Carbon::parse($session->start_time)));
    }

    /**
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function busyWindowsFor(TutorProfile $tutor, string $date): array
    {
        return TeachingSession::query()
            ->where('tutor_profile_id', $tutor->id)
            ->where('date', $date)
            ->where('status', '!=', SessionStatus::Cancelled)
            ->get(['start_time', 'end_time'])
            ->map(fn ($session) => ['start' => Carbon::parse($session->start_time), 'end' => Carbon::parse($session->end_time)])
            ->sortBy(fn ($window) => $window['start']->format('H:i:s'))
            ->values()
            ->all();
    }

    /**
     * Subtract every busy window that overlaps [$windowStart, $windowEnd)
     * from it, returning the remaining free sub-windows in order.
     *
     * @param  list<array{start: Carbon, end: Carbon}>  $busyWindows
     * @return list<array{start_time: string, end_time: string}>
     */
    private function subtractBusy(string $windowStart, string $windowEnd, array $busyWindows): array
    {
        $cursor = Carbon::parse($windowStart);
        $end = Carbon::parse($windowEnd);
        $free = [];

        foreach ($busyWindows as $busy) {
            if ($busy['end']->lte($cursor) || $busy['start']->gte($end)) {
                continue;
            }

            if ($busy['start']->gt($cursor)) {
                $free[] = ['start_time' => $cursor->format('H:i'), 'end_time' => $busy['start']->format('H:i')];
            }

            if ($busy['end']->gt($cursor)) {
                $cursor = $busy['end']->copy();
            }
        }

        if ($cursor->lt($end)) {
            $free[] = ['start_time' => $cursor->format('H:i'), 'end_time' => $end->format('H:i')];
        }

        return $free;
    }
}
