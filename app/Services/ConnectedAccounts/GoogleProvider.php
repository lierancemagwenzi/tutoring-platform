<?php

namespace App\Services\ConnectedAccounts;

use App\Contracts\ConnectedAccountProviderInterface;
use Laravel\Socialite\Facades\Socialite;

class GoogleProvider implements ConnectedAccountProviderInterface
{
    public function getRedirectUrl(string $state): string
    {
        return Socialite::driver('google')
            ->stateless()
            ->scopes([
                'openid', 'profile', 'email',
                // Required for GoogleCalendarService to create/delete the
                // Meet-enabled calendar event a scheduled session's meeting
                // link comes from — without this, every meeting creation
                // fails with "insufficient authentication scopes" even
                // though the account looks connected.
                'https://www.googleapis.com/auth/calendar.events',
            ])
            ->with([
                'state' => $state,
                // Google only issues a refresh_token on a user's very first
                // authorization unless explicitly forced via these two params.
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect()
            ->getTargetUrl();
    }

    public function exchangeCode(string $code): array
    {
        // Socialite reads the authorization code from the current request's
        // query string (the same request Google's redirect delivered it in)
        // rather than from this argument — $code is still accepted here so
        // the interface stays explicit and independently fakeable in tests.
        $googleUser = Socialite::driver('google')->stateless()->user();

        return [
            'provider_user_id' => (string) $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'access_token' => $googleUser->token,
            'refresh_token' => $googleUser->refreshToken,
            'expires_at' => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : null,
            'metadata' => [
                'name' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
            ],
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $token = Socialite::driver('google')->refreshToken($refreshToken);

        return [
            'access_token' => $token->token,
            'expires_at' => $token->expiresIn ? now()->addSeconds($token->expiresIn) : null,
        ];
    }
}
