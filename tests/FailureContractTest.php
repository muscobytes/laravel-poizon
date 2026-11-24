<?php

namespace Muscobytes\Poizon\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Mockery;
use Muscobytes\Poizon\Data\AuthorizationReference;
use Muscobytes\Poizon\Data\CredentialSnapshot;
use Muscobytes\Poizon\Data\Requests\SkuListQuery;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Exceptions\HttpException;
use Muscobytes\Poizon\Exceptions\InvalidRequestException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Exceptions\TransportException;
use Muscobytes\Poizon\Exceptions\UnexpectedIntegrationException;
use Muscobytes\Poizon\PoizonClient;
use Muscobytes\Poizon\Services\AuthorizationManager;
use Muscobytes\Poizon\Services\PoizonJsonTransport;
use Muscobytes\Poizon\Services\PoizonOpenPlatformSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\AbstractLogger;

class FailureContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        $manager = Mockery::mock(AuthorizationManager::class);
        $manager->shouldReceive('resolve')->andReturn(new CredentialSnapshot(7, 'secret-access'));
        $this->app->instance(AuthorizationManager::class, $manager);
    }

    public static function badQueries(): array
    {
        return [[['pageSize' => 0]], [['pageSize' => 2001]], [['pageSize' => '1']], [['dwSkuId' => [1.5]]], [['dwSkuId' => ['9223372036854775808']]],
            [['dwSkuId' => []]], [['distSkuId' => ['key' => 'value']]], [['distSpuId' => [null]]], [['hasPrice' => 'true']], [['sign' => 'secret-sign']]];
    }

    #[DataProvider('badQueries')]
    public function test_invalid_query_has_no_side_effects(array $query): void
    {
        $this->expectException(InvalidRequestException::class);
        try {
            SkuListQuery::fromArray($query);
        } finally {
            Http::assertNothingSent();
            Sleep::assertSleptTimes(0);
        }
    }

    public static function malformed(): array
    {
        return [['not-json'], [['data' => []]], [['code' => '200', 'data' => ['skuList' => []]]], [['code' => 200, 'data' => 'bad']],
            [['code' => 200, 'data' => []]], [['code' => 200, 'data' => ['skuList' => null]]], [['code' => 200, 'data' => ['skuList' => ['key' => []]]]],
            [['code' => 200, 'data' => ['skuList' => [123]]]], [['code' => 200, 'data' => ['skuList' => [['dwSkuId' => 1.5]]]]]];
    }

    #[DataProvider('malformed')]
    public function test_protocol_failure_is_never_retried_or_reported_as_business_refusal(#[\SensitiveParameter] mixed $response): void
    {
        config(['poizon.http.retry.enabled' => true]);
        Http::fake(fn () => Http::response($response));
        try {
            app(PoizonClient::class)->querySkuList(AuthorizationReference::forConnection('third'));
            $this->fail();
        } catch (ProtocolException $e) {
            $this->assertSame(RequestState::ResponseReceived, $e->context->requestState);
            $this->assertSame(1, $e->context->attempts);
            $this->assertStringNotContainsString('not-json', (string) $e);
        }
        Http::assertSentCount(1);
        Sleep::assertSleptTimes(0);
    }

    public static function statuses(): array
    {
        return [[302], [400], [403], [404], [408], [500], [502], [503], [504]];
    }

    #[DataProvider('statuses')]
    public function test_http_failures_never_retry_or_become_protocol_errors(int $status): void
    {
        config(['poizon.http.retry.enabled' => true]);
        Http::fake(fn () => Http::response('secret-upstream-body', $status));
        try {
            app(PoizonClient::class)->querySkuList(AuthorizationReference::forAuthorization(7));
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(1, $e->context->attempts);
            $this->assertSame($status, $e->context->httpStatus);
            $this->assertStringNotContainsString('secret-', (string) $e);
        }
        Http::assertSentCount(1);
        Sleep::assertSleptTimes(0);
    }

    public function test_failed_resigning_does_not_send_again_or_hide_prior_possible_send(): void
    {
        config(['poizon.http.retry.enabled' => true, 'poizon.http.retry.max_retries' => 4]);
        $signer = Mockery::mock(PoizonOpenPlatformSigner::class);
        $signer->shouldReceive('sign')->once()->andReturn('first-sign');
        $signer->shouldReceive('sign')->once()->andThrow(new InvalidRequestException);
        $this->app->instance(PoizonOpenPlatformSigner::class, $signer);
        Http::fake(fn () => Http::failedConnection('secret-network-error'));
        try {
            app(PoizonClient::class)->querySkuList(AuthorizationReference::forConnection('third'));
            $this->fail();
        } catch (InvalidRequestException $e) {
            $this->assertSame(RequestState::PossiblySent, $e->context->requestState);
            $this->assertSame(1, $e->context->attempts);
        }
        Http::assertSentCount(1);
        Sleep::assertSleptTimes(1);
    }

    public function test_invalid_connection_and_query_values_do_not_leak_through_trace_arguments(): void
    {
        foreach ([fn () => AuthorizationReference::forConnection('secret-invalid/connection'),
            fn () => new SkuListQuery(dwSkuId: ['secret-invalid-id'])] as $operation) {
            try {
                $operation();
                $this->fail();
            } catch (InvalidRequestException $exception) {
                $this->assertStringNotContainsString('secret-invalid', json_encode($exception->getTrace()));
                $this->assertStringNotContainsString('secret-invalid', $exception->getMessage());
            }
        }
    }

    public function test_connection_exception_from_payload_preparation_is_safe_and_never_retried(): void
    {
        config(['poizon.http.retry.enabled' => true]);
        Http::fake();
        try {
            app(PoizonJsonTransport::class)->post('https://open.poizon.com/read',
                fn () => throw new ConnectionException('secret-factory-error'), retryRead: true);
            $this->fail();
        } catch (UnexpectedIntegrationException $exception) {
            $this->assertSame(RequestState::NotSent, $exception->context->requestState);
            $this->assertStringNotContainsString('secret-factory-error', (string) $exception);
        }
        Http::assertNothingSent();
        Sleep::assertSleptTimes(0);
    }

    public function test_failure_has_one_terminal_record_with_actual_attempt_count_and_no_sensitive_context(): void
    {
        $logger = new class extends AbstractLogger
        {
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };
        $this->app->instance('poizon.logger', $logger);
        config(['poizon.http.retry.enabled' => true]);
        Http::fake(fn () => Http::failedConnection('secret-network-error'));
        try {
            app(PoizonClient::class)->querySkuList(AuthorizationReference::forConnection('third'));
            $this->fail();
        } catch (TransportException $e) {
            $this->assertSame(3, $e->context->attempts);
        }
        $terminal = array_values(array_filter($logger->records, fn ($record) => $record['message'] === 'poizon.operation.completed'));
        $this->assertCount(1, $terminal);
        $this->assertSame('warning', $terminal[0]['level']);
        $this->assertSame(3, $terminal[0]['context']['attempts']);
        $this->assertStringNotContainsString('secret-', json_encode($logger->records));
    }
}
