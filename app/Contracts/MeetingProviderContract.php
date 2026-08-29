<?php

namespace App\Contracts;

use App\Models\Booking;
use App\Models\SessionMeeting;
use App\Models\TeachingSession;
use Illuminate\Support\Carbon;

interface MeetingProviderContract
{
    /**
     * Create a virtual meeting for the given teaching session. Implementations
     * resolve whatever provider-specific account/credentials they need
     * themselves (e.g. the tutor's connected Google account) — callers never
     * touch provider-specific details directly.
     *
     * @return array{calendar_event_id: ?string, meeting_id: ?string, meeting_url: ?string, organizer_email: ?string, starts_at: ?Carbon, ends_at: ?Carbon, metadata: array<string, mixed>}
     */
    public function createMeeting(TeachingSession $session, Booking $booking): array;

    /**
     * Remove the remote meeting for a cancelled session (e.g. delete the
     * Google Calendar event). Implementations should treat "already gone"
     * as success. Called after the meeting's local status has already been
     * set to Cancelled, so failures here never block session cancellation.
     */
    public function deleteMeeting(TeachingSession $session, SessionMeeting $meeting): void;
}
