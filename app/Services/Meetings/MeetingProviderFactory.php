<?php

namespace App\Services\Meetings;

use App\Contracts\MeetingProviderContract;
use App\Enums\ConnectedAccountProvider;

class MeetingProviderFactory
{
    /**
     * Resolve the concrete meeting provider implementation for the given
     * provider. Resolved through the container (not `new`) so tests can
     * bind a fake implementation, since this now calls real external
     * services (mirrors ConnectedAccountProviderFactory's convention).
     */
    public static function make(ConnectedAccountProvider $provider): MeetingProviderContract
    {
        return match ($provider) {
            ConnectedAccountProvider::Google => app(GoogleCalendarMeetingProvider::class),
        };
    }
}
