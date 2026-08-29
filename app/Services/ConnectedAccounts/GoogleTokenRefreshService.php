<?php

namespace App\Services\ConnectedAccounts;

use App\Enums\ConnectedAccountProvider;
use App\Models\TutorConnectedAccount;
use RuntimeException;

/**
 * Keeps a connected Google account's access token valid — refreshing it
 * via the stored refresh token when it has expired. Callers outside the
 * Connected Accounts domain (e.g. meeting creation) should always go
 * through this rather than reading access_token directly, since a token
 * older than its expires_at is useless against Google's real API.
 */
class GoogleTokenRefreshService
{
    public function validAccessTokenFor(TutorConnectedAccount $account): string
    {
        if ($account->expires_at && $account->expires_at->isFuture()) {
            return $account->access_token;
        }

        if (! $account->refresh_token) {
            throw new RuntimeException('This Google connection has expired and must be reconnected.');
        }

        $fresh = ConnectedAccountProviderFactory::make(ConnectedAccountProvider::Google)
            ->refreshAccessToken($account->refresh_token);

        $account->update([
            'access_token' => $fresh['access_token'],
            'expires_at' => $fresh['expires_at'],
        ]);

        return $fresh['access_token'];
    }
}
