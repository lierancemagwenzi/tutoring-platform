<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Models\Booking;
use App\Models\Lesson;
use App\Models\TeachingSession;
use App\Services\Meetings\MeetingService;
use App\Services\Sessions\SessionContentService;
use RuntimeException;

/**
 * Owns the full Session lifecycle for a booking's purchased package: manual
 * scheduling (one at a time, up to the purchased count), completion, and
 * cancellation. Validation here is defense-in-depth — the primary
 * user-facing validation lives in ScheduleSessionRequest, matching this
 * codebase's established FormRequest-does-business-rules convention.
 */
class SessionSchedulingService
{
    public function __construct(
        private readonly BookingProgressService $progress,
        private readonly TutorAvailabilityService $availability,
        private readonly SessionContentService $content,
        private readonly MeetingService $meetings,
    ) {}

    /**
     * @param  array{lesson_id?: ?int, date: string, start_time: string, end_time: string, tutor_notes?: ?string}  $data
     */
    public function scheduleSession(Booking $booking, array $data): TeachingSession
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new RuntimeException('This booking is not active.');
        }

        if ($this->progress->remainingSessions($booking) <= 0) {
            throw new RuntimeException('This booking has no remaining sessions to schedule.');
        }

        $booking->loadMissing(['tutorProfile', 'service']);

        $session = $this->findOrCreateSession($booking, $data);

        if (! empty($data['lesson_id'])) {
            $this->content->assignLesson($session, Lesson::findOrFail($data['lesson_id']));
        }

        if (array_key_exists('tutor_notes', $data) && $data['tutor_notes'] !== null) {
            $session->update(['tutor_notes' => $data['tutor_notes']]);
        }

        $this->meetings->createForSession($session, $booking);

        return $session->fresh(['service', 'sessionMeeting', 'sessionLessons.lesson', 'bookings']);
    }

    /**
     * @param  array{date: string, start_time: string, end_time: string}  $data
     */
    private function findOrCreateSession(Booking $booking, array $data): TeachingSession
    {
        $tutor = $booking->tutorProfile;
        $service = $booking->service;

        // Matched regardless of status: a Cancelled row at this exact
        // identity still occupies the DB-level unique key, so it must be
        // revived rather than left behind while a duplicate is created.
        $existing = TeachingSession::query()
            ->where('tutor_profile_id', $tutor->id)
            ->where('service_id', $service->id)
            ->where('date', $data['date'])
            ->where('start_time', $data['start_time'])
            ->where('end_time', $data['end_time'])
            ->first();

        if ($existing && $existing->status === SessionStatus::Cancelled) {
            $existing->update(['status' => SessionStatus::Scheduled]);
            // syncWithoutDetaching, not attach: the booking may already be
            // pivoted to this row from before it was cancelled.
            $existing->bookings()->syncWithoutDetaching([$booking->id]);

            return $existing;
        }

        if ($existing) {
            if ($existing->bookings()->where('bookings.id', $booking->id)->exists()) {
                throw new RuntimeException('This session is already scheduled for this booking.');
            }

            $participantsCount = $existing->bookings()->where('status', BookingStatus::Confirmed)->count();

            if ($participantsCount >= $service->max_students_per_session) {
                throw new RuntimeException('This session has already reached its student capacity.');
            }

            $existing->bookings()->attach($booking->id);

            return $existing;
        }

        if (! $this->availability->isWindowAvailable($tutor, $data['date'], $data['start_time'], $data['end_time'])) {
            throw new RuntimeException('You are not available at the requested time.');
        }

        if ($this->availability->hasOverlap($tutor, $data['date'], $data['start_time'], $data['end_time'])) {
            throw new RuntimeException('This overlaps another session you already have scheduled.');
        }

        $session = TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => SessionStatus::Scheduled,
        ]);

        $session->bookings()->attach($booking->id);

        return $session;
    }

    /**
     * Mark a session Completed. Booking package progress (counts) recalculates
     * automatically from the Sessions table — nothing to update there. The
     * Booking's own status is the one exception: once every purchased
     * session has been completed, the booking itself transitions to
     * Completed too (see maybeCompleteBooking) — this is the payout
     * eligibility gate for tutoring-service earnings (PayoutService).
     */
    public function completeSession(TeachingSession $session, ?string $tutorNotes = null): void
    {
        if ($session->status === SessionStatus::Completed) {
            throw new RuntimeException('This session has already been completed.');
        }

        if ($session->status === SessionStatus::Cancelled) {
            throw new RuntimeException('A cancelled session cannot be marked completed.');
        }

        if ($this->hasIncompleteEarlierSession($session)) {
            throw new RuntimeException('Earlier scheduled sessions for this booking must be completed first.');
        }

        $session->update(array_filter([
            'status' => SessionStatus::Completed,
            'completed_at' => now(),
            'tutor_notes' => $tutorNotes,
        ], fn ($value) => $value !== null));

        foreach ($session->bookings as $booking) {
            $this->maybeCompleteBooking($booking);
        }
    }

    /**
     * A booking is fully delivered once every session in its purchased
     * package has been completed — flips it from Confirmed to Completed.
     * Only ever moves forward from Confirmed: a Cancelled/Rejected/Expired
     * booking is never resurrected into Completed just because a
     * previously-scheduled session on it happens to be marked complete.
     */
    private function maybeCompleteBooking(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            return;
        }

        if ($this->progress->completedSessions($booking) >= $this->progress->purchasedSessions($booking)) {
            $booking->update(['status' => BookingStatus::Completed]);
        }
    }

    /**
     * Sessions for the same booking(s) must be completed in chronological
     * order — a tutor can't mark session 3 Completed while session 1 or 2
     * is still outstanding. Cancelled sessions never block, since they were
     * superseded and never happened. Compares against every booking this
     * session is attached to, since a group-class session can be shared.
     */
    private function hasIncompleteEarlierSession(TeachingSession $session): bool
    {
        $bookingIds = $session->bookings()->pluck('bookings.id');

        if ($bookingIds->isEmpty()) {
            return false;
        }

        return TeachingSession::query()
            ->where('id', '!=', $session->id)
            ->whereHas('bookings', fn ($query) => $query->whereIn('bookings.id', $bookingIds))
            ->whereNotIn('status', [SessionStatus::Completed, SessionStatus::Cancelled])
            ->where(function ($query) use ($session) {
                $query->where('date', '<', $session->date->format('Y-m-d'))
                    ->orWhere(function ($query) use ($session) {
                        $query->where('date', $session->date->format('Y-m-d'))
                            ->where('start_time', '<', $session->start_time);
                    });
            })
            ->exists();
    }

    /**
     * Cancel a session (never deleted). Frees the availability window it
     * occupied and lets the booking schedule another session in its place,
     * since BookingProgressService excludes Cancelled sessions from its
     * "scheduled" count. Also cascades to the session's meeting, if any,
     * so a cancelled session doesn't leave a stale Google Calendar event.
     */
    public function cancelSession(TeachingSession $session): void
    {
        if ($session->status === SessionStatus::Completed) {
            throw new RuntimeException('A completed session cannot be cancelled.');
        }

        if ($session->status === SessionStatus::Cancelled) {
            throw new RuntimeException('This session has already been cancelled.');
        }

        $session->update(['status' => SessionStatus::Cancelled]);

        $this->meetings->cancelForSession($session);
    }
}
