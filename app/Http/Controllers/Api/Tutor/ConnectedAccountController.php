<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\ConnectedAccountProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ConnectProviderRequest;
use App\Http\Resources\TutorConnectedAccountResource;
use App\Models\TutorConnectedAccount;
use App\Services\ConnectedAccounts\ConnectedAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ConnectedAccountController extends Controller
{
    /**
     * The logged in tutor's connected accounts.
     */
    public function index(Request $request, ConnectedAccountService $accounts): JsonResponse
    {
        return response()->json([
            'accounts' => TutorConnectedAccountResource::collection($accounts->listFor($request->user()->tutorProfile)),
        ]);
    }

    /**
     * Return the URL to send the tutor's browser to in order to start the
     * provider's OAuth consent flow — a full browser navigation, not an
     * XHR redirect, since the provider's consent screen must be shown
     * directly to the user.
     */
    public function redirect(ConnectProviderRequest $request, string $provider, ConnectedAccountService $accounts): JsonResponse
    {
        $url = $accounts->buildRedirectUrl($request->user()->tutorProfile, ConnectedAccountProvider::from($provider));

        return response()->json(['url' => $url]);
    }

    /**
     * The provider's server redirects the tutor's browser here directly
     * after consent — there is no Sanctum token on a plain browser
     * navigation, so this route is intentionally public. Every outcome
     * (success or failure) ends in a redirect back to the frontend, never
     * a JSON response or an uncaught exception.
     */
    public function callback(Request $request, string $provider, ConnectedAccountService $accounts): RedirectResponse
    {
        $providerEnum = ConnectedAccountProvider::tryFrom($provider);

        if (! $providerEnum) {
            return $this->redirectToFrontend('error', 'This provider is not supported.');
        }

        try {
            $accounts->handleCallback($providerEnum, (string) $request->query('code'), (string) $request->query('state'));
        } catch (RuntimeException $exception) {
            Log::warning('Connected account callback failed.', ['provider' => $provider, 'message' => $exception->getMessage()]);

            return $this->redirectToFrontend('error', $exception->getMessage());
        }

        return $this->redirectToFrontend('connected');
    }

    /**
     * Disconnect a connected account, removing its stored tokens entirely.
     */
    public function disconnect(TutorConnectedAccount $tutorConnectedAccount, ConnectedAccountService $accounts): JsonResponse
    {
        $this->authorize('delete', $tutorConnectedAccount);

        $accounts->disconnect($tutorConnectedAccount);

        return response()->json(['message' => 'Account disconnected.']);
    }

    private function redirectToFrontend(string $status, ?string $message = null): RedirectResponse
    {
        $query = http_build_query(array_filter(['status' => $status, 'message' => $message]));

        return redirect(rtrim((string) config('app.url'), '/')."/tutor/settings/connected-accounts?{$query}");
    }
}
