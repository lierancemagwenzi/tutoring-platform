<?php

namespace App\Services\Meetings;

use App\Enums\MeetingStatus;
use App\Jobs\CancelSessionMeetingJob;
use App\Jobs\CreateSessionMeetingJob;
use App\Models\Booking;
use App\Models\SessionMeeting;
use App\Models\TeachingSession;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orchestrates meeting creation for a teaching session. The only class
 * that knows about the Pending/Scheduled/Failed lifecycle and how it maps
 * onto queue dispatch/retry — BookingConfirmationService just calls
 * createForSession() and moves on, never touching a provider directly.
 */
class MeetingService
{
    /**
     * Create a Pending placeholder meeting for the session (fast, local,
     * transaction-safe) and queue the real provider call for after the
     * enclosing transaction commits — a slow, unreliable third-party call
     * must never run inside (or roll back) the payment transaction.
     * Idempotent: returns the existing meeting if one is already present
     * and still active, and does nothing for a service delivered in-person
     * only. A session revived from Cancelled (rescheduled in its place)
     * carries a Cancelled meeting from before — that row is reset back to
     * Pending and re-dispatched rather than left dangling, since the 1:1
     * teaching_session_id constraint means it must be reused, not replaced.
     */
    public function createForSession(TeachingSession $session, Booking $booking): ?SessionMeeting
    {
        $formatName = strtolower($booking->service->sessionFormat->name);

        if (! in_array($formatName, ['online', 'hybrid'], true)) {
            return null;
        }

        $existing = $session->sessionMeeting;

        if ($existing && $existing->status !== MeetingStatus::Cancelled) {
            return $existing;
        }

        if ($existing) {
            $existing->update([
                'calendar_event_id' => null,
                'meeting_id' => null,
                'meeting_url' => null,
                'organizer_email' => null,
                'starts_at' => null,
                'ends_at' => null,
                'metadata' => null,
                'status' => MeetingStatus::Pending,
            ]);

            $sessionMeeting = $existing;
        } else {
            $sessionMeeting = SessionMeeting::create([
                'teaching_session_id' => $session->id,
                'status' => MeetingStatus::Pending,
            ]);
        }

        CreateSessionMeetingJob::dispatch($sessionMeeting->id)->afterCommit();

        return $sessionMeeting;
    }

    /**
     * Attempt the real provider call for a Pending/Failed meeting. Called
     * by the queued job and by manual retry. A RuntimeException (a
     * permanent precondition failure — no provider selected, no connected
     * account) marks the meeting Failed and is swallowed here; any other
     * exception is left to propagate so the caller's queue retry applies.
     */
    public function attemptCreate(SessionMeeting $sessionMeeting): void
    {
        $session = TeachingSession::with(['tutorProfile.user', 'service.sessionFormat', 'bookings.student'])
            ->find($sessionMeeting->teaching_session_id);

        $booking = $session?->bookings->first();

        if (! $session || ! $booking) {
            $this->markFailed($sessionMeeting, 'No booking could be found for this session.');

            return;
        }

        $provider = $session->tutorProfile->meeting_provider;

        if (! $provider) {
            $this->markFailed($sessionMeeting, 'The tutor has not selected a meeting provider.');

            return;
        }

        try {
            $result = MeetingProviderFactory::make($provider)->createMeeting($session, $booking);
        } catch (RuntimeException $exception) {
            $this->markFailed($sessionMeeting, $exception->getMessage());

            return;
        }

        $sessionMeeting->update([
            'provider' => $provider,
            'calendar_event_id' => $result['calendar_event_id'],
            'meeting_id' => $result['meeting_id'],
            'meeting_url' => $result['meeting_url'],
            'organizer_email' => $result['organizer_email'],
            'starts_at' => $result['starts_at'],
            'ends_at' => $result['ends_at'],
            'metadata' => $result['metadata'],
            'status' => MeetingStatus::Scheduled,
        ]);
    }

    /**
     * Re-queue a Failed (or stuck Pending) meeting for another attempt.
     */
    public function retry(SessionMeeting $sessionMeeting): void
    {
        CreateSessionMeetingJob::dispatch($sessionMeeting->id);
    }

    /**
     * Cancel the meeting for a session that is being cancelled. The local
     * status flips to Cancelled immediately — session cancellation must
     * never be blocked by a slow/unreliable third-party call — and the
     * actual remote event deletion (if one exists) is queued separately.
     * A no-op if the session never had a meeting or it's already Cancelled.
     */
    public function cancelForSession(TeachingSession $session): void
    {
        $sessionMeeting = $session->sessionMeeting;

        if (! $sessionMeeting || $sessionMeeting->status === MeetingStatus::Cancelled) {
            return;
        }

        $hadRemoteEvent = (bool) $sessionMeeting->calendar_event_id;

        $sessionMeeting->update(['status' => MeetingStatus::Cancelled]);

        if ($hadRemoteEvent) {
            CancelSessionMeetingJob::dispatch($sessionMeeting->id)->afterCommit();
        }
    }

    /**
     * Attempt to delete the real, remote meeting (e.g. Google Calendar
     * event) for an already-locally-Cancelled meeting. Called by the queued
     * job and mirrors attemptCreate()'s exception handling: a RuntimeException
     * (permanent precondition failure — no connected account left) is logged
     * and swallowed, any other exception propagates for queue retry.
     */
    public function attemptCancel(SessionMeeting $sessionMeeting): void
    {
        $session = TeachingSession::with('tutorProfile')->find($sessionMeeting->teaching_session_id);

        if (! $session) {
            return;
        }

        $provider = $sessionMeeting->provider;

        if (! $provider) {
            return;
        }

        try {
            MeetingProviderFactory::make($provider)->deleteMeeting($session, $sessionMeeting);
        } catch (RuntimeException $exception) {
            Log::warning('Meeting deletion failed.', [
                'session_meeting_id' => $sessionMeeting->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    private function markFailed(SessionMeeting $sessionMeeting, string $reason): void
    {
        Log::warning('Meeting creation failed.', ['session_meeting_id' => $sessionMeeting->id, 'reason' => $reason]);

        $sessionMeeting->update([
            'status' => MeetingStatus::Failed,
            'metadata' => [...($sessionMeeting->metadata ?? []), 'error' => $reason],
        ]);
    }
}
