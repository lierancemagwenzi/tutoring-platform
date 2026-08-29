<?php

namespace App\Services\Meetings;

use App\Contracts\MeetingProviderContract;
use App\Enums\ConnectedAccountProvider;
use App\Models\Booking;
use App\Models\SessionMeeting;
use App\Models\TeachingSession;
use App\Services\ConnectedAccounts\GoogleTokenRefreshService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleCalendarMeetingProvider implements MeetingProviderContract
{
    public function __construct(
        private readonly GoogleTokenRefreshService $tokens,
        private readonly GoogleCalendarService $calendar,
    ) {}

    /**
     * Create a real Google Calendar event (with a Google Meet conference
     * requested) using the tutor's connected Google account. Throws a plain
     * RuntimeException for permanent preconditions (no connected account) —
     * MeetingService treats that as non-retryable. Any other exception
     * (a genuine, transient Google/HTTP failure) is left to propagate so
     * the caller's queue retry mechanism can take over.
     */
    public function createMeeting(TeachingSession $session, Booking $booking): array
    {
        $account = $session->tutorProfile->connectedAccounts()
            ->where('provider', ConnectedAccountProvider::Google)
            ->first();

        if (! $account) {
            throw new RuntimeException('The tutor has not connected a Google account.');
        }

        $accessToken = $this->tokens->validAccessTokenFor($account);

        $timezone = config('app.timezone');
        $startsAt = Carbon::parse("{$session->date->format('Y-m-d')} {$session->start_time}", $timezone);
        $endsAt = Carbon::parse("{$session->date->format('Y-m-d')} {$session->end_time}", $timezone);

        $event = $this->calendar->createEvent($accessToken, [
            'summary' => $booking->service->title,
            'description' => $this->description($session, $booking),
            'start' => ['dateTime' => $startsAt->toRfc3339String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $endsAt->toRfc3339String(), 'timeZone' => $timezone],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => (string) Str::uuid(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ]);

        $videoEntryPoint = collect($event['conferenceData']['entryPoints'] ?? [])
            ->firstWhere('entryPointType', 'video');

        return [
            'calendar_event_id' => $event['id'] ?? null,
            'meeting_id' => $event['conferenceData']['conferenceId'] ?? null,
            'meeting_url' => $videoEntryPoint['uri'] ?? $event['hangoutLink'] ?? null,
            'organizer_email' => $event['organizer']['email'] ?? $account->email,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'metadata' => [
                'conference_data' => $event['conferenceData'] ?? null,
                'html_link' => $event['htmlLink'] ?? null,
            ],
        ];
    }

    /**
     * Delete the Google Calendar event backing a cancelled session's
     * meeting. A no-op if there's nothing to delete or no connected account
     * left to delete it with — the local meeting is already Cancelled by
     * the time this runs, so there's nothing to roll back either way.
     */
    public function deleteMeeting(TeachingSession $session, SessionMeeting $meeting): void
    {
        if (! $meeting->calendar_event_id) {
            return;
        }

        $account = $session->tutorProfile->connectedAccounts()
            ->where('provider', ConnectedAccountProvider::Google)
            ->first();

        if (! $account) {
            return;
        }

        $accessToken = $this->tokens->validAccessTokenFor($account);

        $this->calendar->deleteEvent($accessToken, $meeting->calendar_event_id);
    }

    /**
     * A session may have more than one booking (a group lesson) — list
     * every attendee rather than just the booking that triggered this
     * particular meeting-creation attempt.
     */
    private function description(TeachingSession $session, Booking $booking): string
    {
        $students = $session->bookings->pluck('student')->filter()
            ->map(fn ($student) => "{$student->first_name} {$student->last_name}")
            ->implode(', ');

        $lines = [
            "Tutor: {$session->tutorProfile->display_name}",
            'Student(s): '.($students !== '' ? $students : "{$booking->student->first_name} {$booking->student->last_name}"),
            "Booking Reference: #{$booking->id}",
        ];

        if ($booking->message) {
            $lines[] = "Notes: {$booking->message}";
        }

        return implode("\n", $lines);
    }
}
