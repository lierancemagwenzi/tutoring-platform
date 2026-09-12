<?php

namespace Tests\Feature\Tutor;

use App\Services\ConnectedAccounts\GoogleProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Tests\TestCase;

/**
 * Google's consent screen lets a user deselect individual (sensitive)
 * scopes and still complete the OAuth flow — these cover
 * GoogleProvider::exchangeCode() actually checking what was granted,
 * rather than trusting the exchange succeeding at all.
 */
class GoogleProviderScopeTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(array $approvedScopes): SocialiteUser
    {
        return SocialiteUser::fake([
            'id' => 'google-123',
            'email' => 'teacher@gmail.com',
            'name' => 'Test Teacher',
            'approvedScopes' => $approvedScopes,
        ]);
    }

    private function mockSocialiteDriver(SocialiteUser $user): void
    {
        $driver = \Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    public function test_exchange_code_rejects_a_connection_that_never_granted_calendar_access(): void
    {
        $this->mockSocialiteDriver($this->fakeGoogleUser(['openid', 'profile', 'email']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("calendar access wasn't granted");

        app(GoogleProvider::class)->exchangeCode('any-code');
    }

    public function test_exchange_code_succeeds_when_calendar_scope_was_granted(): void
    {
        $this->mockSocialiteDriver($this->fakeGoogleUser([
            'openid', 'profile', 'email', 'https://www.googleapis.com/auth/calendar.events',
        ]));

        $result = app(GoogleProvider::class)->exchangeCode('any-code');

        $this->assertSame('google-123', $result['provider_user_id']);
        $this->assertSame('teacher@gmail.com', $result['email']);
    }
}
