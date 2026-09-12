<?php

namespace App\Services\ConnectedAccounts;

use App\Enums\ConnectedAccountProvider;
use App\Models\TutorConnectedAccount;
use App\Models\TutorProfile;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Orchestrates connecting/disconnecting third-party accounts. Never talks
 * to Google (or any provider) directly — that's entirely behind
 * ConnectedAccountProviderFactory/ConnectedAccountProviderInterface, so
 * this class stays identical regardless of which providers exist.
 */
class ConnectedAccountService
{
    /**
     * How long a "connect" attempt's signed state remains valid for.
     */
    private const STATE_TTL_MINUTES = 10;

    /**
     * @return Collection<int, TutorConnectedAccount>
     */
    public function listFor(TutorProfile $tutor): Collection
    {
        return $tutor->connectedAccounts;
    }

    /**
     * Build the URL to redirect the tutor's browser to in order to start
     * the OAuth consent flow, embedding a signed, self-verifying state that
     * lets the (unauthenticated, provider-initiated) callback recover which
     * tutor started this — this app has no session to store state in.
     */
    public function buildRedirectUrl(TutorProfile $tutor, ConnectedAccountProvider $provider): string
    {
        $state = encrypt([
            'tutor_profile_id' => $tutor->id,
            'nonce' => Str::random(40),
            'expires_at' => now()->addMinutes(self::STATE_TTL_MINUTES)->timestamp,
        ]);

        return ConnectedAccountProviderFactory::make($provider)->getRedirectUrl($state);
    }

    /**
     * Verify the callback's state, exchange the authorization code for
     * tokens, and persist (or update) the connected account.
     *
     * @throws RuntimeException if the state is invalid, expired, or the
     *                          provider exchange fails.
     */
    public function handleCallback(ConnectedAccountProvider $provider, string $code, string $state): TutorConnectedAccount
    {
        try {
            $payload = decrypt($state);
        } catch (DecryptException) {
            throw new RuntimeException('This connection request is invalid or has expired.');
        }

        if (! is_array($payload) || ($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('This connection request is invalid or has expired.');
        }

        $tutor = TutorProfile::find($payload['tutor_profile_id'] ?? null);

        if (! $tutor) {
            throw new RuntimeException('This connection request is invalid or has expired.');
        }

        try {
            $tokenData = ConnectedAccountProviderFactory::make($provider)->exchangeCode($code);
        } catch (RuntimeException $exception) {
            // A provider throws this itself for a specific, tutor-safe
            // reason it wants surfaced verbatim (e.g. GoogleProvider's
            // missing-calendar-scope check) — pass it straight through
            // rather than replacing it with the generic message below.
            throw $exception;
        } catch (Throwable $exception) {
            // Never surface the provider's raw error (e.g. a Google/Guzzle
            // HTTP exception body) to the frontend — log it for us, show
            // the tutor a clean, generic message instead.
            Log::warning('Connected account provider exchange failed.', [
                'provider' => $provider->value,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('This account could not be connected. Please try again.');
        }

        return TutorConnectedAccount::updateOrCreate(
            ['tutor_profile_id' => $tutor->id, 'provider' => $provider],
            [
                'provider_user_id' => $tokenData['provider_user_id'],
                'email' => $tokenData['email'],
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_at' => $tokenData['expires_at'],
                'metadata' => $tokenData['metadata'],
                'connected_at' => now(),
            ],
        );
    }

    public function disconnect(TutorConnectedAccount $account): void
    {
        $tutor = $account->tutorProfile;

        if ($tutor->meeting_provider === $account->provider) {
            $tutor->update(['meeting_provider' => null]);
        }

        $account->delete();
    }
}
