<?php

// Observe the existing SDK without network, credentials or application bootstrap.
require dirname(__DIR__, 4).'/vendor/autoload.php';

use Muscobytes\Poizon\Data\Requests\SkuPriceQuery;
use Muscobytes\Poizon\Data\Sku;
use Muscobytes\Poizon\Data\SkuPrice;
use Muscobytes\Poizon\Data\SkuPriceData;
use Muscobytes\Poizon\Exceptions\InvalidRequestException;

$checks = [];
$check = static function (string $name, bool $value) use (&$checks): void {
    if (! $value) {
        throw new RuntimeException('Unexpected SDK observation: '.$name);
    }
    $checks[$name] = true;
};
$check('positive_price_exact', SkuPrice::fromArray(['minBidPrice' => 36900])->minBidPrice === '36900');
$zero = SkuPrice::fromArray(['minBidPrice' => 0]);
$check('zero_present_distinct', $zero->minBidPrice === '0' && $zero->has('minBidPrice'));
$null = SkuPrice::fromArray(['minBidPrice' => null]);
$check('null_present', $null->minBidPrice === null && $null->has('minBidPrice'));
$missing = SkuPrice::fromArray([]);
$check('missing_price_distinct', $missing->minBidPrice === null && ! $missing->has('minBidPrice'));
$check('missing_item_distinct', SkuPriceData::fromArray(['items' => []])->items === []);
$check('status_preserved_extra', Sku::fromArray(['distStatus' => 'PRODUCT_OFF'])->extraFields['distStatus'] === 'PRODUCT_OFF');
$check('has_price_false_preserved', (new SkuPriceQuery(dwSkuIds: [65412613], hasPrice: false))->parameters()['hasPrice'] === false);
$check('batch_200_accepted', count((new SkuPriceQuery(dwSkuIds: range(1, 200)))->parameters()['dwSkuIds']) === 200);
$rejected = false;
try {
    new SkuPriceQuery(dwSkuIds: range(1, 201));
} catch (InvalidRequestException) {
    $rejected = true;
}
$check('batch_201_rejected', $rejected);
echo json_encode(['php' => PHP_VERSION, 'checks' => $checks, 'count' => count($checks)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
