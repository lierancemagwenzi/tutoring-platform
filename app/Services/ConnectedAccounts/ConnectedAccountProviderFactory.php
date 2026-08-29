<?php

namespace App\Services\ConnectedAccounts;

use App\Contracts\ConnectedAccountProviderInterface;
use App\Enums\ConnectedAccountProvider;

class ConnectedAccountProviderFactory
{
    /**
     * Resolve the concrete connected-account provider implementation for
     * the given provider. Resolved through the container (not `new`) so
     * tests can bind a fake implementation in place of the real one, since
     * unlike the mock meeting providers, these call real external services.
     */
    public static function make(ConnectedAccountProvider $provider): ConnectedAccountProviderInterface
    {
        return match ($provider) {
            ConnectedAccountProvider::Google => app(GoogleProvider::class),
        };
    }
}
