<?php

namespace App\Services\Meetings;

use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for Google Calendar's REST API — the one place that
 * actually talks to Google for meeting creation. A raw Http-facade call
 * rather than the google/apiclient SDK, mirroring how this codebase already
 * calls other external services (see App\Services\H5p\H5PService).
 */
class GoogleCalendarService
{
    /**
     * Create a Calendar event on the tutor's primary calendar, requesting a
     * Google Meet conference as part of it (`conferenceDataVersion=1`).
     *
     * @param  array<string, mixed>  $eventPayload
     * @return array<string, mixed>
     */
    public function createEvent(string $accessToken, array $eventPayload): array
    {
        return Http::withToken($accessToken)
            ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1', $eventPayload)
            ->throw()
            ->json();
    }

    /**
     * Delete a Calendar event on the tutor's primary calendar. A 404/410
     * response means the event is already gone (deleted manually, or a
     * retried delete) and is treated as success rather than an error.
     */
    public function deleteEvent(string $accessToken, string $eventId): void
    {
        $response = Http::withToken($accessToken)
            ->delete("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$eventId}");

        if ($response->failed() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }
    }
}
