<?php

namespace Muscobytes\Poizon\Tests;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Mockery;
use Muscobytes\Poizon\Data\AuthorizationReference;
use Muscobytes\Poizon\Data\CallContext;
use Muscobytes\Poizon\Data\CreateOrderResponse;
use Muscobytes\Poizon\Data\CredentialSnapshot;
use Muscobytes\Poizon\Data\Requests\CreateOrderRequest;
use Muscobytes\Poizon\Data\Requests\OrderProduct;
use Muscobytes\Poizon\Data\Requests\OrderQuery;
use Muscobytes\Poizon\Data\Requests\ShippingAddress;
use Muscobytes\Poizon\Data\Requests\SkuDetailQuery;
use Muscobytes\Poizon\Data\Requests\SkuListQuery;
use Muscobytes\Poizon\Data\Requests\SkuPriceQuery;
use Muscobytes\Poizon\Data\Requests\SpuListQuery;
use Muscobytes\Poizon\Data\SkuListResponse;
use Muscobytes\Poizon\Enums\AuthorizationReason;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Exceptions\ApiException;
use Muscobytes\Poizon\Exceptions\AuthorizationException;
use Muscobytes\Poizon\Exceptions\ConfigurationException;
use Muscobytes\Poizon\Exceptions\HttpException;
use Muscobytes\Poizon\Exceptions\InvalidRequestException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Exceptions\RateLimitException;
use Muscobytes\Poizon\Exceptions\TransportException;
use Muscobytes\Poizon\PoizonClient;
use Muscobytes\Poizon\Services\AuthorizationManager;
use Muscobytes\Poizon\Services\PoizonOpenPlatformSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\AbstractLogger;

class ClientTest extends TestCase
{
    private array $pauses = [];

    private AuthorizationManager $credentials;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Sleep::fake();
        Sleep::whenFakingSleep(function ($duration): void {
            $this->pauses[] = (int) $duration->totalMilliseconds;
            $this->travel(1)->milliseconds();
        });
        $this->credentials = Mockery::mock(AuthorizationManager::class);
        $this->credentials->shouldReceive('resolve')->andReturn(new CredentialSnapshot(17, 'private-access'));
        $this->app->instance(AuthorizationManager::class, $this->credentials);
    }

    public function test_typed_nested_dtos_preserve_unknown_fields_presence_and_large_ids(): void
    {
        Http::fake(fn () => Http::response(['code' => 200, 'msg' => 'upstream message', 'data' => ['skuList' => [[
            'dwSkuId' => '9007199254740993', 'distSkuId' => '9223372036854775808', 'minBidPrice' => 36600,
            'fulfillmentMode' => 'FUTURE_MODE', 'saleAttr' => [['enName' => 'Color', 'enValue' => 'Blue']],
            'futureField' => ['new' => true],
        ]], 'total' => 1]]));
        $result = $this->client()->querySkuList(AuthorizationReference::forConnection('Third.account'));
        $this->assertInstanceOf(SkuListResponse::class, $result);
        $sku = $result->data->skuList[0];
        $this->assertSame('9007199254740993', $sku->dwSkuId);
        $this->assertSame('9223372036854775808', $sku->distSkuId);
        $this->assertSame('36600', $sku->minBidPrice);
        $this->assertSame('FUTURE_MODE', $sku->fulfillmentMode);
        $this->assertSame(['new' => true], $sku->extraFields['futureField']);
        $this->assertFalse($sku->has('distSpuId'));
        $this->assertSame('upstream message', $result->message);
    }

    public static function operations(): array
    {
        return [
            ['queryPrepaymentBalance', null, ['imoneyDTO' => ['amount' => '123.45', 'minUnitVal' => 12345, 'currency' => 'CNY']]],
            ['querySkuList', new SkuListQuery(dwSkuId: ['9007199254740993']), ['skuList' => []]],
            ['querySpuList', new SpuListQuery, ['spuList' => []]],
            ['querySkuDetail', new SkuDetailQuery(dwSpuId: 1), ['skuList' => []]],
            ['querySkuBidPrice', new SkuPriceQuery(dwSkuIds: [1]), ['items' => []]],
            ['queryOrderInfo', new OrderQuery(pageNum: 2001, distOrderNo: []), ['orderList' => [], 'total' => 0]],
            ['queryLogisticsTrace', 'customer-order', ['subOrderTraceInfo' => []]],
        ];
    }

    #[DataProvider('operations')]
    public function test_all_reads_default_to_one_send_and_can_retry_with_fresh_signatures(string $method, mixed $request, array $data): void
    {
        $args = $request === null ? [] : [$request];
        $bodies = [];
        Http::fake(function ($request) use (&$bodies, $data) {
            $bodies[] = $request->data();
            if (count($bodies) < 3) {
                return Http::failedConnection('private-request-body');
            }

            return Http::response(['code' => 200, 'data' => $data]);
        });
        try {
            $this->client()->{$method}(AuthorizationReference::forConnection('third'), ...$args);
            $this->fail();
        } catch (TransportException $e) {
            $this->assertSame(RequestState::PossiblySent, $e->context->requestState);
        }
        $this->assertCount(1, $bodies);
        $this->assertSame([], $this->pauses);
        config(['poizon.http.retry.enabled' => true]);
        $bodies = [];
        $result = $this->client()->{$method}(AuthorizationReference::forAuthorization(17), ...$args);
        $this->assertSame(3, $result->metadata->attempts);
        $this->assertSame([500, 1000], $this->pauses);
        $this->assertCount(3, array_unique(array_column($bodies, 'sign')));
        foreach ($bodies as $body) {
            $signature = $body['sign'];
            unset($body['sign']);
            $this->assertSame('private-access', $body['access_token']);
            $this->assertSame($signature, (new PoizonOpenPlatformSigner)->sign($body, 'test-secret'));
        }
    }

    public static function retryCounts(): array
    {
        return [[0, []], [1, [500]], [2, [500, 1000]], [4, [500, 1000, 1000, 1000]]];
    }

    #[DataProvider('retryCounts')]
    public function test_configured_retry_count_and_delays_are_independent(int $retries, array $pauses): void
    {
        config(['poizon.http.retry.enabled' => true, 'poizon.http.retry.max_retries' => (string) $retries]);
        Http::fake(fn () => Http::failedConnection('private-token'));
        try {
            $this->client()->querySkuList(AuthorizationReference::forConnection('third'));
            $this->fail();
        } catch (TransportException $e) {
            $this->assertStringNotContainsString('private-', (string) $e);
        }
        Http::assertSentCount($retries + 1);
        $this->assertSame($pauses, $this->pauses);
    }

    public function test_business_error_contract_is_identical_for_pinned_and_named_authorizations(): void
    {
        Http::fake(fn () => Http::response(['code' => 40900006, 'msg' => 'private-access unavailable', 'data' => []]));
        foreach ([AuthorizationReference::forConnection('third'), AuthorizationReference::forAuthorization(17)] as $reference) {
            try {
                $this->client()->querySkuList($reference);
                $this->fail();
            } catch (ApiException $e) {
                $this->assertSame(40900006, $e->response()->code);
                $this->assertStringNotContainsString('private-access', $e->response()->message);
                $this->assertSame(RequestState::ResponseReceived, $e->context->requestState);
            }
        }
        Http::assertSentCount(2);
    }

    public function test_rate_limit_backoff_applies_even_when_retries_are_disabled(): void
    {
        Http::fake(fn () => Http::response(['code' => 429], 429, ['Retry-After' => '30']));
        foreach ([429, null] as $status) {
            try {
                $this->client()->querySkuList(AuthorizationReference::forConnection('third'));
                $this->fail();
            } catch (RateLimitException $e) {
                $this->assertSame($status, $e->context->httpStatus);
                $this->assertSame(30, $e->context->retryAfter);
            }
        }
        Http::assertSentCount(1);
    }

    public function test_403_does_not_revoke_credentials_but_401_rejects_only_the_used_pair(): void
    {
        $this->credentials->shouldReceive('rejectAccessTokenForAuthorization')->once()->with(17, 'private-access')->andThrow(new AuthorizationException(AuthorizationReason::Rejected));
        Http::fakeSequence()->push([], 403)->push([], 401);
        try {
            $this->client()->querySkuList(AuthorizationReference::forAuthorization(17));
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->context->httpStatus);
        }
        try {
            $this->client()->querySkuList(AuthorizationReference::forAuthorization(17));
            $this->fail();
        } catch (AuthorizationException $e) {
            $this->assertSame(401, $e->context->httpStatus);
        }
    }

    public function test_malformed_data_is_protocol_failure_and_create_order_is_always_single_send(): void
    {
        config(['poizon.http.retry.enabled' => true, 'poizon.http.retry.max_retries' => 4]);
        Http::fake(fn () => Http::response(['code' => 200, 'data' => ['skuList' => 'bad']]));
        try {
            $this->client()->querySkuList(AuthorizationReference::forConnection('third'));
            $this->fail();
        } catch (ProtocolException $e) {
            $this->assertSame(RequestState::ResponseReceived, $e->context->requestState);
        }
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::failedConnection());
        try {
            $this->client()->createOrder(AuthorizationReference::forAuthorization(17), self::purchase());
            $this->fail();
        } catch (TransportException) {
        }
        Http::assertSentCount(1);
    }

    public function test_logger_is_safe_single_terminal_correlated_and_cannot_change_result(): void
    {
        $logger = new class extends AbstractLogger
        {
            public array $records = [];

            public bool $throws = false;

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [$level, $message, $context];
                if ($this->throws) {
                    throw new \RuntimeException('private-log-error');
                }
            }
        };
        $this->app->instance('poizon.logger', $logger);
        Http::fake(fn () => Http::response(['code' => 200, 'msg' => 'private-access', 'data' => ['skuList' => []]]));
        $id = '019306c5-a190-7d61-8dd6-e7bf01c46001';
        $result = $this->client()->querySkuList(AuthorizationReference::forConnection('third'), context: new CallContext($id));
        $this->assertCount(1, $logger->records);
        $this->assertSame($result->metadata->operationId, $logger->records[0][2]['operation_id']);
        $this->assertSame($id, $logger->records[0][2]['correlation_id']);
        $this->assertStringNotContainsString('private-', json_encode($logger->records));
        $logger->throws = true;
        $this->assertSame([], $this->client()->querySkuList(AuthorizationReference::forConnection('third'))->data->skuList);
    }

    public function test_invalid_retry_configuration_and_reserved_query_fields_never_send(): void
    {
        foreach ([['max_retries' => -1], ['max_retries' => 'abc'], ['delays_ms' => []], ['delays_ms' => [-1]], ['enabled' => 'invalid']] as $invalid) {
            config(['poizon.http.retry' => $invalid + ['enabled' => false, 'max_retries' => 2, 'delays_ms' => [500, 1000]]]);
            try {
                $this->client()->querySkuList(AuthorizationReference::forConnection('third'));
                $this->fail();
            } catch (ConfigurationException) {
            }
        }
        Http::assertNothingSent();
        $this->expectException(InvalidRequestException::class);
        SkuListQuery::fromArray(['access_token' => 'private-token']);
    }

    public function test_purchase_response_is_dto_without_claiming_full_purchase(): void
    {
        Http::fake(fn () => Http::response(['code' => 200, 'data' => ['orderItems' => []]]));
        $result = $this->client()->createOrder(AuthorizationReference::forAuthorization(17), self::purchase());
        $this->assertInstanceOf(CreateOrderResponse::class, $result);
        $this->assertSame([], $result->data->orderItems);
    }

    public static function purchase(): CreateOrderRequest
    {
        return new CreateOrderRequest(shippingAddress: new ShippingAddress('Warehouse', 'a@example.test', '123', 'P', 'C', 'D', 'Street'), product: new OrderProduct('123', '456', 3, '12050'), requestId: str_repeat('a', 32));
    }

    private function client(): PoizonClient
    {
        return app(PoizonClient::class);
    }
}
