<?php

namespace Muscobytes\Poizon\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Services\PoizonOpenPlatformSigner;
use PHPUnit\Framework\Attributes\DataProvider;

class SignerTest extends TestCase
{
    private array $pauses = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        Sleep::whenFakingSleep(function ($duration): void {
            $this->pauses[] = (int) $duration->totalMilliseconds;
        });
        config()->set('poizon.open_platform', [
            'app_key' => 'demo-app-key',
            'app_secret' => 'demo-app-secret',
            'base_url' => 'https://open.poizon.com',
            'language' => 'en',
            'time_zone' => 'Asia/Shanghai',
        ]);
        $this->freezeTime();
    }

    public function test_public_signature_vector_is_reproducible(): void
    {
        // Published demo values, not credentials for the RAKETA application.
        $signature = (new PoizonOpenPlatformSigner)->sign([
            'app_key' => '70ef4f430d664eb69db4dcaeda651111',
            'timeZone' => 'Asia/Shanghai',
            'language' => 'ru',
            'timestamp' => 1760688019605,
        ], 'd62dd3a409104a19bfa006eca2af2222');

        $this->assertSame('83E9709C5DF4197963BB712D9C9317F8', $signature);
    }

    public function test_signature_uses_sorted_scalar_business_parameters_and_client_encoding(): void
    {
        $signer = new PoizonOpenPlatformSigner;
        $this->assertSame(strtoupper(md5("a=a+b%2B%2F!'()*~&active=false&empty=&quantity=0demo")), $signer->sign([
            'quantity' => 0,
            'empty' => '',
            'active' => false,
            'a' => "a b+/!'()*~",
        ], 'demo'));
    }

    public function test_nested_objects_follow_official_client_insertion_order_and_omit_nulls(): void
    {
        $this->assertSame('7D3A37287977A4B19766FF3B655A1A2F', (new PoizonOpenPlatformSigner)->sign([
            'product' => ['dwSpuId' => 123, 'dwSkuId' => 9007199254740991, 'distSpuId' => 's/中文',
                'distSkuId' => 'a b', 'quantity' => 3, 'price' => 12000, 'ignored' => null],
            'shippingAddress' => ['name' => "a b+/!'()*~", 'city' => '广州', 'ignored' => null],
            'requestId' => '12345678901234567890123456789012',
        ], 'demo'));
    }

    #[DataProvider('arraySignatureVectors')]
    public function test_primitive_arrays_match_official_client_vectors(array $business, string $signature): void
    {
        $this->assertSame($signature, (new PoizonOpenPlatformSigner)->sign(array_merge([
            'app_key' => 'dummy-app-key', 'access_token' => 'dummy-access-token', 'timestamp' => 1763424000000,
            'language' => 'en', 'timeZone' => 'Asia/Shanghai',
        ], $business), 'dummy-array-secret'));
    }

    public static function arraySignatureVectors(): array
    {
        return [
            [['dwSkuIds' => [614353360, 111738607], 'hasPrice' => false], 'A8FCAA04AA7CE5723652300D6792909C'],
            [['dwSkuIds' => ['9007199254740993', '9223372036854775807'], 'hasPrice' => false], '7AC31572DF878236F3334EABFC0F17D3'],
            [['distSkuId' => ['a b', 'a+b', "~!*'()", '中文'], 'hasPrice' => true], '578D668FA543C93846D786E539F2B033'],
        ];
    }

    #[DataProvider('unsupportedSignatureValues')]
    public function test_unconfirmed_values_are_rejected_by_signer(mixed $value): void
    {
        $this->expectException(PoizonException::class);
        (new PoizonOpenPlatformSigner)->sign(['ids' => $value], 'demo');
    }

    public static function unsupportedSignatureValues(): array
    {
        return [[null], [1.5], [[null]], [[1.5]], [[[123]]], [['name' => "\xff"]]];
    }
}
