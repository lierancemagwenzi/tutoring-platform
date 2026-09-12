<?php

namespace App\Services\Meetings;

use App\Contracts\MeetingProviderContract;
use App\Enums\ConnectedAccountProvider;
use App\Models\Booking;
use App\Models\SessionMeeting;
use App\Models\TeachingSession;
use App\Services\ConnectedAccounts\GoogleTokenRefreshService;
use Illuminate\Http\Client\RequestException;
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
        // TEMPORARY: existing connected accounts predate the calendar.events
        // scope fix (see GoogleProvider::exchangeCode()) and every real call
        // fails with a 403 until each tutor reconnects Google. Until that's
        // rolled out, GOOGLE_FAKE_MEETINGS lets the rest of the booking/
        // session flow (and demos) proceed with a placeholder link instead
        // of a guaranteed failure. Remove this branch once real accounts are
        // reconnected — nothing else needs to change to restore real calls.
        if (config('services.google.fake_meetings')) {
            return $this->fakeMeeting($session);
        }

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

        try {
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
        } catch (RequestException $exception) {
            // A 403 here means the connected account's access token lacks
            // Calendar scope — GoogleProvider::exchangeCode() now blocks
            // this for new connections, but an account connected before
            // that check existed (or one where Google silently dropped the
            // scope on refresh) can still hit it. Retrying changes nothing
            // without the tutor re-granting access, so this is a permanent
            // failure, not a transient one — any other status is left to
            // propagate for the queue's normal retry.
            if ($exception->response->status() === 403) {
                if ($exception->response->json('error.errors.0.reason') === 'accessNotConfigured') {
                    throw new RuntimeException(
                        'The Google Calendar API is not enabled for this app\'s Google Cloud project. Enable it in Google Cloud Console, then retry.',
                    );
                }

                throw new RuntimeException(
                    "The tutor's Google account doesn't have calendar permission. Reconnect Google and allow calendar access to create meeting links.",
                );
            }

            throw $exception;
        }

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
        // See the matching TEMPORARY note in createMeeting() — a fake
        // meeting's calendar_event_id was never real, so there's nothing on
        // Google's side to delete.
        if (config('services.google.fake_meetings')) {
            return;
        }

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
     * See the TEMPORARY note in createMeeting(). Produces the same shape
     * MeetingService expects from a real provider, so nothing downstream
     * (status, notifications, the tutor/student meeting link display) can
     * tell the difference.
     *
     * @return array<string, mixed>
     */
    private function fakeMeeting(TeachingSession $session): array
    {
        $timezone = config('app.timezone');
        $startsAt = Carbon::parse("{$session->date->format('Y-m-d')} {$session->start_time}", $timezone);
        $endsAt = Carbon::parse("{$session->date->format('Y-m-d')} {$session->end_time}", $timezone);
        $id = (string) Str::uuid();

        return [
            'calendar_event_id' => "fake-{$id}",
            'meeting_id' => Str::substr($id, 0, 12),
            'meeting_url' => "https://meet.google.com/fake-{$id}",
            'organizer_email' => $session->tutorProfile->user->email,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'metadata' => ['fake' => true],
        ];
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
