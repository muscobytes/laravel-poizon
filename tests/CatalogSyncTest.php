<?php

namespace Muscobytes\Poizon\Tests;

use Illuminate\Support\Facades\Http;
use Mockery;
use Muscobytes\Poizon\Data\AuthorizationReference;
use Muscobytes\Poizon\Data\CredentialSnapshot;
use Muscobytes\Poizon\Data\Requests\SkuListQuery;
use Muscobytes\Poizon\Data\Requests\SkuPriceQuery;
use Muscobytes\Poizon\Data\Requests\SpuListQuery;
use Muscobytes\Poizon\Data\Spu;
use Muscobytes\Poizon\Exceptions\InvalidRequestException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\PoizonClient;
use Muscobytes\Poizon\Services\AuthorizationManager;
use Muscobytes\Poizon\Services\PoizonOpenPlatformSigner;
use PHPUnit\Framework\Attributes\DataProvider;

class CatalogSyncTest extends TestCase
{
    public static function windows(): array
    {
        return [
            [[]],
            [['modifyStartTime' => null, 'modifyEndTime' => null]],
            [['modifyStartTime' => 0]],
            [['modifyEndTime' => '1787786149000']],
            [['modifyStartTime' => '1787786149000', 'modifyEndTime' => 1787786149000]],
            [['modifyStartTime' => 1787786149001, 'modifyEndTime' => 1787786149000]],
            [['modifyStartTime' => '9223372036854775807']],
        ];
    }

    #[DataProvider('windows')]
    public function test_windows_share_validation_and_omit_null_in_both_creation_paths(array $window): void
    {
        $expected = array_map(static fn ($value) => (int) $value, array_filter($window, static fn ($value) => $value !== null));
        foreach ([SpuListQuery::class, SkuListQuery::class] as $class) {
            $constructed = new $class(...$window);
            $fromArray = $class::fromArray($window);
            $this->assertSame($expected, $constructed->parameters());
            $this->assertSame($expected, $fromArray->parameters());
            $this->assertSame($expected['modifyStartTime'] ?? null, $constructed->modifyStartTime);
            $this->assertSame($expected['modifyEndTime'] ?? null, $fromArray->modifyEndTime);
        }
    }

    public static function invalidWindowValues(): array
    {
        return array_map(static fn ($value) => [$value], [true, false, 1787786149000.0, -1, '-1', '1.5', '1e3', '', [], '9223372036854775808']);
    }

    #[DataProvider('invalidWindowValues')]
    public function test_raw_window_types_are_rejected_before_php_coercion(mixed $value): void
    {
        foreach ([SpuListQuery::class, SkuListQuery::class] as $class) {
            foreach (['modifyStartTime', 'modifyEndTime'] as $field) {
                foreach (['constructor', 'fromArray'] as $path) {
                    try {
                        $path === 'constructor' ? new $class(...[$field => $value]) : $class::fromArray([$field => $value]);
                        $this->fail('Invalid raw timestamp was accepted.');
                    } catch (InvalidRequestException) {
                        $this->addToAssertionCount(1);
                    }
                }
            }
        }
    }

    public static function querySkuValues(): array
    {
        return [[true], [false], [null]];
    }

    #[DataProvider('querySkuValues')]
    public function test_query_sku_preserves_false_and_omits_null(?bool $value): void
    {
        $expected = $value === null ? [] : ['querySku' => $value];
        foreach ([new SpuListQuery(querySku: $value), SpuListQuery::fromArray(['querySku' => $value])] as $query) {
            $this->assertSame($expected, $query->parameters());
            $this->assertSame($value, $query->querySku);
        }
    }

    public static function invalidBooleans(): array
    {
        return [[0], [1], ['false'], ['true'], [''], [0.0], [[]]];
    }

    #[DataProvider('invalidBooleans')]
    public function test_query_sku_does_not_coerce_weak_caller_values(mixed $value): void
    {
        foreach (['constructor', 'fromArray'] as $path) {
            try {
                $path === 'constructor' ? new SpuListQuery(querySku: $value) : SpuListQuery::fromArray(['querySku' => $value]);
                $this->fail('Invalid raw boolean was accepted.');
            } catch (InvalidRequestException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_existing_positional_queries_and_validation_remain_compatible(): void
    {
        $spu = new SpuListQuery('12', 2000, ['10903'], ['opaque'], 'platform');
        $sku = new SkuListQuery('12', 2000, ['10903'], ['65412613'], ['opaque'], ['sku'], false, 'platform');
        $this->assertSame(['startId' => 12, 'pageSize' => 2000, 'dwSpuId' => [10903], 'distSpuId' => ['opaque'], 'distPlatform' => 'platform'], $spu->parameters());
        $this->assertSame(false, $sku->parameters()['hasPrice']);
        $this->assertArrayNotHasKey('modifyStartTime', $sku->parameters());
        $this->assertArrayNotHasKey('querySku', $spu->parameters());
        foreach ([['pageSize' => null], ['querySku' => null, 'access_token' => null], ['distStatus' => 'PRODUCT_OFF']] as $invalid) {
            try {
                SpuListQuery::fromArray($invalid);
                $this->fail('Existing invalid input changed behavior.');
            } catch (InvalidRequestException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_catalog_parameters_reach_the_signed_http_body_without_loss(): void
    {
        $this->mockAuthorization();
        Http::fake(fn () => Http::response(['code' => 200, 'data' => ['spuList' => []]]));
        app(PoizonClient::class)->querySpuList(AuthorizationReference::forConnection('catalog-global'), new SpuListQuery(querySku: false, modifyStartTime: '1787786149000', modifyEndTime: 1787786149000));
        Http::assertSent(function ($request) {
            $body = $request->data();
            $sign = $body['sign'];
            unset($body['sign']);

            return $body['querySku'] === false
                && $body['modifyStartTime'] === 1787786149000
                && $body['modifyEndTime'] === 1787786149000
                && $sign === (new PoizonOpenPlatformSigner)->sign($body, 'test-secret');
        });
        Http::assertSentCount(1);
    }

    public function test_spu_fixture_preserves_description_cursor_and_nested_unknown_fields(): void
    {
        $this->mockAuthorization();
        Http::fake(fn () => Http::response(file_get_contents(__DIR__.'/fixtures/catalog/spu-list.json')));
        $result = app(PoizonClient::class)->querySpuList(AuthorizationReference::forConnection('catalog-global'), new SpuListQuery(dwSpuId: [10903], querySku: true));
        $data = $result->data;
        $spu = $data->spuList[0];
        $this->assertSame(1, $data->total);
        $this->assertFalse($data->has('lastId'));
        $this->assertFalse($data->has('hasMore'));
        $this->assertSame('10903', $spu->dwSpuId);
        $this->assertSame(311943, $spu->extraFields['id']);
        $this->assertSame('Control description', $spu->extraFields['productDesc']);
        $this->assertSame('PRODUCT_ON', $spu->extraFields['distStatus']);
        $this->assertSame('65412648', $spu->skuList[0]->dwSkuId);
        $this->assertFalse($spu->skuList[0]->has('dwSpuId'));
        $this->assertFalse($spu->skuList[0]->has('minBidPrice'));
    }

    public function test_sku_window_and_price_fixtures_preserve_no_price_without_claiming_delisting(): void
    {
        $this->mockAuthorization();
        Http::fakeSequence()->push(file_get_contents(__DIR__.'/fixtures/catalog/sku-list.json'))
            ->push(file_get_contents(__DIR__.'/fixtures/catalog/prices-all.json'));
        $client = app(PoizonClient::class);
        $sku = $client->querySkuList(AuthorizationReference::forConnection('catalog-global'), SkuListQuery::fromArray(['dwSkuId' => [65412648], 'modifyStartTime' => '1787181701000']))->data->skuList[0];
        $this->assertSame('PRODUCT_ON', $sku->extraFields['distStatus']);
        $this->assertFalse($sku->has('minBidPrice'));
        $prices = $client->querySkuBidPrice(AuthorizationReference::forConnection('catalog-global'), new SkuPriceQuery(dwSkuIds: [65412613, 65412626, 65412648]));
        $this->assertSame('36700', $prices->data->items[0]->minBidPrice);
        $this->assertSame('39400', $prices->data->items[1]->minBidPrice);
        $this->assertSame('65412648', $prices->data->items[2]->dwSkuId);
        $this->assertFalse($prices->data->items[2]->has('minBidPrice'));
        $this->assertNull($prices->data->items[2]->minBidPrice);
        $this->assertSame([], $prices->data->items[2]->extraFields['supplementPrice']);
        Http::assertSent(fn ($request) => ($request->data()['modifyStartTime'] ?? null) === 1787181701000);
        Http::assertSentCount(2);
    }

    public static function priceFilters(): array
    {
        return [[true, 'prices-present', [65412613, 65412626]], [false, 'prices-missing', [65412648]]];
    }

    #[DataProvider('priceFilters')]
    public function test_price_filter_fixtures_keep_primary_offer_presence_separate_from_catalog_status(bool $hasPrice, string $fixture, array $ids): void
    {
        $this->mockAuthorization();
        Http::fake(fn () => Http::response(file_get_contents(__DIR__.'/fixtures/catalog/'.$fixture.'.json')));
        $response = app(PoizonClient::class)->querySkuBidPrice(AuthorizationReference::forConnection('catalog-global'), new SkuPriceQuery(dwSkuIds: [65412613, 65412626, 65412648], hasPrice: $hasPrice));
        $this->assertSame(array_map('strval', $ids), array_column($response->data->items, 'dwSkuId'));
        foreach ($response->data->items as $item) {
            $this->assertSame($hasPrice, $item->has('minBidPrice'));
        }
        Http::assertSent(fn ($request) => $request->data()['hasPrice'] === $hasPrice);
    }

    public function test_numeric_dist_spu_ids_are_normalized_without_narrowing_existing_strings(): void
    {
        foreach ([0 => '0', 12345 => '12345', PHP_INT_MAX => (string) PHP_INT_MAX] as $input => $expected) {
            $this->assertSame($expected, Spu::fromArray(['distSpuId' => $input])->distSpuId);
        }
        foreach (['', 'opaque-dist-id', '9223372036854775808'] as $value) {
            $this->assertSame($value, Spu::fromArray(['distSpuId' => $value])->distSpuId);
        }
        foreach ([-1, 12345.0, true, false, []] as $value) {
            try {
                Spu::fromArray(['distSpuId' => $value]);
                $this->fail('Invalid numeric dist ID accepted.');
            } catch (ProtocolException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_raw_json_big_ids_and_null_presence_survive_the_http_decoder(): void
    {
        $this->mockAuthorization();
        Http::fake(fn () => Http::response('{"code":200,"data":{"spuList":[{"dwSpuId":9007199254740993,"distSpuId":9223372036854775808,"image":null,"future":{"enabled":false}}]}}'));
        $spu = app(PoizonClient::class)->querySpuList(AuthorizationReference::forConnection('catalog-global'))->data->spuList[0];
        $this->assertSame('9007199254740993', $spu->dwSpuId);
        $this->assertSame('9223372036854775808', $spu->distSpuId);
        $this->assertTrue($spu->has('image'));
        $this->assertNull($spu->image);
        $this->assertFalse($spu->has('dwSpuTitle'));
        $this->assertSame(['enabled' => false], $spu->extraFields['future']);
    }

    private function mockAuthorization(): void
    {
        $credentials = Mockery::mock(AuthorizationManager::class);
        $credentials->shouldReceive('resolve')->andReturn(new CredentialSnapshot(17, 'private-access'));
        $this->app->instance(AuthorizationManager::class, $credentials);
    }
}
