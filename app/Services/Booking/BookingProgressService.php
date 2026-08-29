<?php

namespace App\Services\Booking;

use App\Enums\SessionStatus;
use App\Models\Booking;

/**
 * Computes a booking's package progress entirely from the Sessions table —
 * no counters are stored on Booking itself, so this is always the single
 * source of truth, even as sessions get scheduled/completed/cancelled.
 */
class BookingProgressService
{
    /**
     * How many sessions the purchased service package includes in total.
     */
    public function purchasedSessions(Booking $booking): int
    {
        return $booking->service->sessions_included;
    }

    /**
     * How many sessions currently occupy a slot toward the purchased count
     * — a Cancelled session frees its slot back up, so it's excluded.
     */
    public function scheduledSessions(Booking $booking): int
    {
        return $booking->teachingSessions()->where('status', '!=', SessionStatus::Cancelled)->count();
    }

    /**
     * How many of this booking's sessions have already been delivered.
     */
    public function completedSessions(Booking $booking): int
    {
        return $booking->teachingSessions()->where('status', SessionStatus::Completed)->count();
    }

    /**
     * How many more sessions the tutor can still schedule for this booking.
     */
    public function remainingSessions(Booking $booking): int
    {
        return max(0, $this->purchasedSessions($booking) - $this->scheduledSessions($booking));
    }

    /**
     * How many of this booking's sessions are scheduled and still upcoming.
     */
    public function upcomingSessions(Booking $booking): int
    {
        return $booking->teachingSessions()->where('status', SessionStatus::Scheduled)->count();
    }

    /**
     * @return array<string, int>
     */
    public function progress(Booking $booking): array
    {
        return [
            'purchased_sessions' => $this->purchasedSessions($booking),
            'scheduled_sessions' => $this->scheduledSessions($booking),
            'completed_sessions' => $this->completedSessions($booking),
            'remaining_sessions' => $this->remainingSessions($booking),
            'upcoming_sessions' => $this->upcomingSessions($booking),
        ];
    }
}
