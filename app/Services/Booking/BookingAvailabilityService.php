<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Service;
use App\Models\TutorProfile;
use Illuminate\Support\Carbon;

class BookingAvailabilityService
{
    /**
     * Return a map of date (Y-m-d) to bookable slots (start time + the availability slot
     * it falls within, since a date may have more than one slot) for the given tutor,
     * service, and month.
     *
     * @return array<string, list<array{start_time: string, availability_slot_id: int}>>
     */
    public function availableTimesForMonth(TutorProfile $tutor, Service $service, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today()->toDateString();

        $availabilityDates = $tutor->availabilityDates()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('date', '>=', $today)
            ->with('slots')
            ->orderBy('date')
            ->get();

        $result = [];

        foreach ($availabilityDates as $availabilityDate) {
            $times = [];

            foreach ($availabilityDate->slots as $slot) {
                $times = array_merge($times, $this->bookableStartTimesForSlot($service, $slot, $availabilityDate->date->toDateString()));
            }

            if (! empty($times)) {
                $result[$availabilityDate->date->toDateString()] = $times;
            }
        }

        return $result;
    }

    /**
     * Determine whether the given start time is currently bookable within the given slot.
     */
    public function isTimeAvailable(
        Service $service,
        AvailabilitySlot $slot,
        string $date,
        string $startTime,
        ?int $excludeBookingId = null,
    ): bool {
        // Normalize before comparing: a start time sourced from a freshly-loaded Eloquent
        // attribute may come back as "09:00:00" (MySQL normalizes TIME columns on read)
        // while the candidate times below are always generated as "09:00" — without this,
        // the strict string comparison silently never matches on MySQL (SQLite, used in
        // the test suite, stores the literal string given and never hits this mismatch).
        $normalizedStartTime = Carbon::parse($startTime)->format('H:i');
        $times = array_column($this->bookableStartTimesForSlot($service, $slot, $date, $excludeBookingId), 'start_time');

        return in_array($normalizedStartTime, $times, true);
    }

    /**
     * Compute the bookable start times within a single availability slot for a given date.
     *
     * @return list<array{start_time: string, availability_slot_id: int}>
     */
    private function bookableStartTimesForSlot(
        Service $service,
        AvailabilitySlot $slot,
        string $date,
        ?int $excludeBookingId = null,
    ): array {
        $duration = $service->session_duration_minutes;
        $slotEnd = Carbon::parse($slot->end_time);
        $cursor = Carbon::parse($slot->start_time);

        $times = [];

        while ($cursor->copy()->addMinutes($duration)->lte($slotEnd)) {
            $startTime = $cursor->format('H:i');

            if ($this->hasCapacity($service, $slot, $date, $startTime, $excludeBookingId)) {
                $times[] = ['start_time' => $startTime, 'availability_slot_id' => $slot->id];
            }

            $cursor->addMinutes($duration);
        }

        return $times;
    }

    /**
     * Determine whether a given (service, slot, date, start time) combination still has
     * capacity for another booking, based on existing bookings holding a seat.
     *
     * A booking is accepted straight into AwaitingPayment (there is no
     * persisted "Accepted" state once a request moves forward) — Accepted
     * is still checked defensively in case anything ever sets it directly.
     */
    private function hasCapacity(
        Service $service,
        AvailabilitySlot $slot,
        string $date,
        string $startTime,
        ?int $excludeBookingId = null,
    ): bool {
        $count = Booking::query()
            ->where('service_id', $service->id)
            ->where('availability_slot_id', $slot->id)
            ->where('date', $date)
            ->where('start_time', $startTime)
            ->whereIn('status', [BookingStatus::Accepted->value, BookingStatus::AwaitingPayment->value, BookingStatus::Confirmed->value])
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->count();

        return $count < $service->max_students_per_session;
    }
}
