<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;

interface ConnectedAccountProviderInterface
{
    /**
     * Build the provider's OAuth authorization URL, embedding the given
     * (already-signed) state value so the callback can verify it later.
     */
    public function getRedirectUrl(string $state): string;

    /**
     * Exchange an authorization code for the connected account's tokens
     * and profile info.
     *
     * @return array{provider_user_id: string, email: string, access_token: string, refresh_token: ?string, expires_at: ?Carbon, metadata: array<string, mixed>}
     */
    public function exchangeCode(string $code): array;

    /**
     * Exchange a refresh token for a fresh access token.
     *
     * @return array{access_token: string, expires_at: ?Carbon}
     */
    public function refreshAccessToken(string $refreshToken): array;
}
