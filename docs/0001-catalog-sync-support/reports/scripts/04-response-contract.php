<?php

// Historical baseline 1898169f1fd0e94e909f4ab8950481da6fde0173; run in Docker, never against Poizon.
// Task 05 changes numeric Spu.distSpuId acceptance; current regressions live in tests/CatalogSyncTest.php.
require dirname(__DIR__, 4).'/vendor/autoload.php';

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Muscobytes\Poizon\Data\AuthorizationReference;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Data\Sku;
use Muscobytes\Poizon\Data\Spu;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Internal\Backoff;
use Muscobytes\Poizon\Internal\ResponseDecoder;

$checks = [];
$check = function (string $name, bool $passes) use (&$checks): void {
    if (! $passes) {
        throw new RuntimeException('Unexpected SDK behavior: '.$name);
    }
    $checks[$name] = true;
};

$decoder = new ResponseDecoder(new Backoff);
foreach (['9007199254740993', '9223372036854775808'] as $digits) {
    $json = '{"code":200,"data":{"skuList":[{"dwSkuId":'.$digits.',"distSkuId":"'.$digits.'"}]}}';
    $body = $decoder->envelope(new Response(new PsrResponse(200, [], $json)), new ErrorContext);
    $sku = Sku::fromArray($body['data']['skuList'][0]);
    $check('numeric_json_id_'.$digits, $sku->dwSkuId === $digits && $sku->distSkuId === $digits);
}

$spu = Spu::fromArray([
    'dwSpuId' => '9007199254740993',
    'distSpuId' => 'opaque-dist-id',
    'productDesc' => '<p>synthetic description</p>',
    'modifyTime' => 1795800000000,
    'futureField' => ['nullable' => null, 'enabled' => false],
    'image' => null,
    'skuList' => [['dwSkuId' => '9223372036854775808', 'barcode' => 'synthetic-barcode']],
]);
$check('description_in_extra_fields', $spu->extraFields['productDesc'] === '<p>synthetic description</p>');
$check('timestamp_in_extra_fields', $spu->extraFields['modifyTime'] === 1795800000000);
$check('unknown_nested_null_and_false', $spu->extraFields['futureField'] === ['nullable' => null, 'enabled' => false]);
$check('explicit_null_presence', $spu->has('image') && $spu->image === null);
$check('missing_field_presence', ! $spu->has('dwSpuTitle') && $spu->dwSpuTitle === null);
$check('unknown_field_presence', $spu->has('productDesc') && in_array('productDesc', $spu->fields(), true));
$check('nested_sku_extra_fields', $spu->skuList[0]->extraFields['barcode'] === 'synthetic-barcode');
$check('opaque_dist_id_preserved', $spu->distSpuId === 'opaque-dist-id');

$rejected = false;
try {
    Spu::fromArray(['distSpuId' => 12345]);
} catch (ProtocolException $e) {
    $rejected = $e->field === 'distSpuId';
}
$check('numeric_spu_dist_id_currently_rejected', $rejected);
$check('numeric_string_spu_dist_id_accepted', Spu::fromArray(['distSpuId' => '12345'])->distSpuId === '12345');

$rejected = false;
try {
    Sku::fromArray(['dwSkuId' => 9007199254740992.0]);
} catch (ProtocolException) {
    $rejected = true;
}
$check('float_dw_id_rejected', $rejected);
$check('unknown_fulfillment_mode_preserved', Sku::fromArray(['fulfillmentMode' => 'FUTURE_MODE'])->fulfillmentMode === 'FUTURE_MODE');
$check('connection_reference_preserved', AuthorizationReference::forConnection('catalog-fast')->connection === 'catalog-fast');

echo json_encode(['php' => PHP_VERSION, 'checks' => $checks, 'count' => count($checks)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
