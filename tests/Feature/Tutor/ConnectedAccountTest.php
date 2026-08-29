<?php

namespace Tests\Feature\Tutor;

use App\Contracts\ConnectedAccountProviderInterface;
use App\Models\TutorConnectedAccount;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\ConnectedAccounts\GoogleProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConnectedAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://127.0.0.1:8000/api/tutor/settings/connected-accounts/google/callback',
        ]);
    }

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function bindFakeGoogleProvider(): void
    {
        $this->app->bind(GoogleProvider::class, fn () => new class implements ConnectedAccountProviderInterface
        {
            public function getRedirectUrl(string $state): string
            {
                return 'https://accounts.google.com/fake?state='.$state;
            }

            public function exchangeCode(string $code): array
            {
                return [
                    'provider_user_id' => 'google-123',
                    'email' => 'teacher@gmail.com',
                    'access_token' => 'fake-access-token',
                    'refresh_token' => 'fake-refresh-token',
                    'expires_at' => now()->addHour(),
                    'metadata' => ['name' => 'Test Teacher'],
                ];
            }

            public function refreshAccessToken(string $refreshToken): array
            {
                return ['access_token' => 'refreshed-token', 'expires_at' => now()->addHour()];
            }
        });
    }

    private function validState(int $tutorProfileId): string
    {
        return encrypt([
            'tutor_profile_id' => $tutorProfileId,
            'nonce' => Str::random(40),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
    }

    public function test_tutor_can_list_own_connected_accounts(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor->user);

        $response = $this->getJson('/api/tutor/settings/connected-accounts');

        $response->assertOk();
        $response->assertJsonCount(0, 'accounts');
    }

    public function test_redirect_returns_a_google_authorization_url_containing_a_signed_state(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor->user);

        $response = $this->getJson('/api/tutor/settings/connected-accounts/google/redirect');

        $response->assertOk();
        $this->assertStringContainsString('accounts.google.com', $response->json('url'));
        $this->assertStringContainsString('state=', $response->json('url'));
    }

    public function test_redirect_rejects_an_unsupported_provider(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor->user);

        $response = $this->getJson('/api/tutor/settings/connected-accounts/microsoft/redirect');

        $response->assertUnprocessable();
    }

    public function test_callback_with_valid_state_and_code_creates_a_connected_account(): void
    {
        $this->bindFakeGoogleProvider();
        $tutor = $this->tutor();
        $state = $this->validState($tutor->id);

        $response = $this->get('/api/tutor/settings/connected-accounts/google/callback?'.http_build_query([
            'code' => 'fake-code',
            'state' => $state,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('status=connected', $response->headers->get('Location'));

        $this->assertDatabaseCount('tutor_connected_accounts', 1);

        $account = TutorConnectedAccount::first();
        $this->assertSame($tutor->id, $account->tutor_profile_id);
        $this->assertSame('google', $account->provider->value);
        $this->assertSame('teacher@gmail.com', $account->email);
        $this->assertNotNull($account->connected_at);

        // Encrypted casts round-trip transparently on the model, but the raw
        // DB column must not contain the plaintext token.
        $rawValue = DB::table('tutor_connected_accounts')->value('access_token');
        $this->assertStringNotContainsString('fake-access-token', $rawValue);
    }

    public function test_callback_with_tampered_state_redirects_with_error_and_creates_nothing(): void
    {
        $this->bindFakeGoogleProvider();
        $this->tutor();

        $response = $this->get('/api/tutor/settings/connected-accounts/google/callback?'.http_build_query([
            'code' => 'fake-code',
            'state' => 'not-a-real-encrypted-value',
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('status=error', $response->headers->get('Location'));
        $this->assertDatabaseCount('tutor_connected_accounts', 0);
    }

    public function test_callback_with_expired_state_redirects_with_error(): void
    {
        $this->bindFakeGoogleProvider();
        $tutor = $this->tutor();

        $expiredState = encrypt([
            'tutor_profile_id' => $tutor->id,
            'nonce' => Str::random(40),
            'expires_at' => now()->subMinute()->timestamp,
        ]);

        $response = $this->get('/api/tutor/settings/connected-accounts/google/callback?'.http_build_query([
            'code' => 'fake-code',
            'state' => $expiredState,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('status=error', $response->headers->get('Location'));
        $this->assertDatabaseCount('tutor_connected_accounts', 0);
    }

    public function test_reconnecting_updates_the_existing_row_instead_of_creating_a_duplicate(): void
    {
        $this->bindFakeGoogleProvider();
        $tutor = $this->tutor();

        $firstState = $this->validState($tutor->id);
        $this->get('/api/tutor/settings/connected-accounts/google/callback?'.http_build_query(['code' => 'code-1', 'state' => $firstState]));

        $secondState = $this->validState($tutor->id);
        $this->get('/api/tutor/settings/connected-accounts/google/callback?'.http_build_query(['code' => 'code-2', 'state' => $secondState]));

        $this->assertDatabaseCount('tutor_connected_accounts', 1);
    }

    public function test_tutor_can_disconnect_a_connected_account(): void
    {
        $tutor = $this->tutor();
        $account = TutorConnectedAccount::create([
            'tutor_profile_id' => $tutor->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
            'email' => 'teacher@gmail.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'connected_at' => now(),
        ]);

        Sanctum::actingAs($tutor->user);

        $response = $this->deleteJson("/api/tutor/settings/connected-accounts/{$account->id}");

        $response->assertOk();
        $this->assertDatabaseCount('tutor_connected_accounts', 0);
    }

    public function test_tutor_cannot_disconnect_another_tutors_connected_account(): void
    {
        $owner = $this->tutor();
        $account = TutorConnectedAccount::create([
            'tutor_profile_id' => $owner->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
            'email' => 'teacher@gmail.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'connected_at' => now(),
        ]);

        $intruder = $this->tutor();
        Sanctum::actingAs($intruder->user);

        $response = $this->deleteJson("/api/tutor/settings/connected-accounts/{$account->id}");

        $response->assertForbidden();
        $this->assertDatabaseCount('tutor_connected_accounts', 1);
    }

    public function test_student_cannot_access_any_connected_account_routes(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/tutor/settings/connected-accounts')->assertForbidden();
        $this->getJson('/api/tutor/settings/connected-accounts/google/redirect')->assertForbidden();
    }
}
