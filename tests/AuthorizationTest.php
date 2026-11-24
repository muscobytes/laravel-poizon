<?php

namespace Muscobytes\Poizon\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Muscobytes\Poizon\Contracts\AuthorizationRequiredNotifier;
use Muscobytes\Poizon\Data\AuthorizationReference;
use Muscobytes\Poizon\Data\TokenSet;
use Muscobytes\Poizon\Enums\AuthorizationReason;
use Muscobytes\Poizon\Enums\CredentialFailure;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Events\OAuthFailed;
use Muscobytes\Poizon\Exceptions\AuthorizationException;
use Muscobytes\Poizon\Exceptions\ConfigurationException;
use Muscobytes\Poizon\Exceptions\CredentialStoreException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Exceptions\TransportException;
use Muscobytes\Poizon\Models\PoizonAuthorization;
use Muscobytes\Poizon\PoizonClient;
use Muscobytes\Poizon\Services\AuthorizationManager;
use Muscobytes\Poizon\Services\OAuthClient;

class AuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Artisan::call('migrate', ['--force' => true]);
        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function test_migration_preserves_existing_ids_ciphertext_and_history(): void
    {
        $this->manager()->save('Third.account', $this->tokens());
        $before = (array) DB::table('poizon_authorizations')->sole();
        $this->assertNotSame('private-access', $before['access_token']);
        Artisan::call('migrate', ['--force' => true]);
        $this->assertSame($before, (array) DB::table('poizon_authorizations')->sole());
        $this->assertSame(1, DB::table('migrations')->where('migration', '2026_11_17_210000_create_poizon_authorizations')->count());
        $this->assertSame('private-access', $this->manager()->resolve(AuthorizationReference::forConnection('Third.account'))->accessToken());
    }

    public function test_shared_refresh_is_once_and_historical_order_uses_pinned_authorization(): void
    {
        foreach (['first', 'second', 'Third.account'] as $connection) {
            $this->manager()->save($connection, $this->tokens());
        }
        $id = $this->manager()->authorizationId('Third.account');
        $this->assertSame(1, PoizonAuthorization::count());
        PoizonAuthorization::sole()->update(['access_token_expires_at' => now()->subMinute()]);
        config(['poizon.open_platform.redirect_uri' => null]);
        Http::fake(fn () => Http::response($this->tokenResponse()));
        $this->manager()->refreshDue();
        Http::assertSentCount(1);
        $this->assertSame('rotated-access', $this->manager()->accessToken('second'));
        $this->manager()->save('Third.account', $this->tokens('new-account'));
        $this->assertNotSame($id, $this->manager()->authorizationId('Third.account'));
        $statuses = $this->manager()->statuses(['absent', 'third.account']);
        $this->assertSame('missing', $statuses->items['third.account']->status);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['code' => 200, 'data' => ['orderList' => []]]));
        app(PoizonClient::class)->queryOrderInfo(AuthorizationReference::forAuthorization($id));
        Http::assertSent(fn ($r) => $r['access_token'] === 'rotated-access');
    }

    public function test_unknown_rotation_never_retries_and_notifies_once_for_shared_accounts(): void
    {
        $notifier = new class implements AuthorizationRequiredNotifier
        {
            public array $calls = [];

            public function notify(int $id, array $connections, string $reason): bool
            {
                $this->calls[] = [$id, $connections, $reason];
                throw new \RuntimeException('private-notifier-error');
            }
        };
        $this->app->instance(AuthorizationRequiredNotifier::class, $notifier);
        config(['poizon.http.retry.enabled' => true, 'poizon.http.retry.max_retries' => 4]);
        foreach (['one', 'two', 'three'] as $name) {
            $this->manager()->save($name, $this->tokens());
        }
        PoizonAuthorization::sole()->update(['access_token_expires_at' => now()->subMinute()]);
        Http::fake(fn () => Http::failedConnection('private-request'));
        foreach (['one', 'two', 'three'] as $name) {
            try {
                $this->manager()->accessToken($name);
                $this->fail();
            } catch (AuthorizationException $e) {
                $this->assertSame(AuthorizationReason::RefreshUncertain, $e->reason);
            }
        }
        Http::assertSentCount(1);
        $this->assertTrue(PoizonAuthorization::sole()->refresh_pending);
        $this->assertSame('unknown', PoizonAuthorization::sole()->problem_notification_status);
        $this->assertCount(1, $notifier->calls);
        $this->assertEqualsCanonicalizing(['one', 'two', 'three'], $notifier->calls[0][1]);
    }

    public function test_bad_configuration_before_refresh_does_not_revoke_or_set_pending(): void
    {
        $this->manager()->save('third', $this->tokens());
        PoizonAuthorization::sole()->update(['access_token_expires_at' => now()->subMinute()]);
        config(['poizon.open_platform.app_secret' => null]);
        try {
            $this->manager()->accessToken('third');
            $this->fail();
        } catch (ConfigurationException) {
        }
        $this->assertFalse(PoizonAuthorization::sole()->refresh_pending);
        $this->assertSame('active', PoizonAuthorization::sole()->status);
        $this->assertNull(PoizonAuthorization::sole()->problem_notified_at);
        Http::assertNothingSent();
    }

    public function test_oauth_returns_protected_dto_and_diagnostics_never_carry_tokens(): void
    {
        Http::fake(fn () => Http::response($this->tokenResponse()));
        $tokens = app(OAuthClient::class)->exchange('private-code');
        $this->assertInstanceOf(TokenSet::class, $tokens);
        $this->assertSame('rotated-access', $tokens->accessToken());
        $this->assertStringNotContainsString('rotated-access', print_r($tokens, true));
        try {
            serialize($tokens);
            $this->fail();
        } catch (\LogicException) {
        }
        Event::fake([OAuthFailed::class]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['code' => 200, 'data' => ['access_token' => 'private-access']]));
        try {
            app(OAuthClient::class)->exchange('private-code');
            $this->fail();
        } catch (ProtocolException) {
        }
        Event::assertDispatched(OAuthFailed::class, fn ($e) => ! str_contains(json_encode($e->context->toArray()), 'private-'));
    }

    public function test_default_notifier_is_unconfirmed_and_https_redirect_is_service_independent(): void
    {
        $this->assertFalse(app(AuthorizationRequiredNotifier::class)->notify(1, ['third'], 'access_rejected'));
        $this->assertStringContainsString(rawurlencode('https://another-service.example/oauth/callback'), app(OAuthClient::class)->authorizationUrl('one-time-state'));
        config(['poizon.open_platform.redirect_uri' => 'http://another-service.example/callback']);
        $this->expectException(ConfigurationException::class);
        app(OAuthClient::class)->authorizationUrl('state');
    }

    public function test_status_storage_failure_is_safe_without_a_mutex_or_original_exception(): void
    {
        Schema::drop('cache_locks');
        $this->assertSame('missing', $this->manager()->statuses(['third'])->items['third']->status);
        Schema::drop('poizon_connections');
        try {
            $this->manager()->statuses(['private-connection']);
            $this->fail();
        } catch (CredentialStoreException $e) {
            $this->assertSame(CredentialFailure::Database, $e->reason);
            $this->assertNull($e->getPrevious());
            $this->assertStringNotContainsString('select', $e->getMessage());
            $this->assertStringNotContainsString('private-connection', json_encode($e->getTrace()));
        }
    }

    public function test_oauth_and_authorization_preserve_the_number_of_failed_sends(): void
    {
        Http::fake(fn () => Http::failedConnection('private-network-error'));
        foreach ([fn () => app(OAuthClient::class)->exchange('private-code'),
            fn () => app(OAuthClient::class)->refresh('private-refresh'),
            fn () => $this->manager()->authorize('third', 'private-code')] as $operation) {
            try {
                $operation();
                $this->fail();
            } catch (TransportException $e) {
                $this->assertSame(1, $e->context->attempts);
                $this->assertSame(RequestState::PossiblySent, $e->context->requestState);
            }
        }
        Http::assertSentCount(3);
    }

    private function tokens(string $id = 'shared-account'): TokenSet
    {
        return new TokenSet($id, 'private-access', 'private-refresh', now()->addHour()->toDateTimeImmutable(), now()->addDay()->toDateTimeImmutable(), 'all');
    }

    private function tokenResponse(): array
    {
        return ['code' => 200, 'data' => ['open_id' => 'shared-account', 'access_token' => 'rotated-access', 'refresh_token' => 'rotated-refresh',
            'access_token_expires_in' => 3600, 'refresh_token_expires_in' => 7200, 'scope' => ['all']]];
    }

    private function manager(): AuthorizationManager
    {
        return app(AuthorizationManager::class);
    }
}
