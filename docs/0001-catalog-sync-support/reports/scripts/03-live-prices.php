<?php

// Append after 01-live-bootstrap.php and stream to PHP in the existing app container.
// Only this explicit projection may leave the runtime; never print raw responses or exceptions.
$sample = static function (array $row): array {
    $keys = ['id', 'dwSpuId', 'dwSkuId', 'distSpuId', 'distSkuId', 'distStatus', 'modifyTime', 'minBidPrice', 'fulfillmentMode', 'currency'];
    $values = array_intersect_key($row, array_flip($keys));
    if (array_key_exists('supplementPrice', $row)) {
        $values['supplementPrice'] = is_array($row['supplementPrice'])
            ? array_map(static fn ($tier) => is_array($tier) ? array_intersect_key($tier, ['priceType' => true, 'price' => true]) : ['unexpected_type' => get_debug_type($tier)], $row['supplementPrice'])
            : ($row['supplementPrice'] === null ? null : ['unexpected_type' => get_debug_type($row['supplementPrice'])]);
    }

    return ['fields' => array_keys($row), 'selected' => $values, 'types' => array_map(get_debug_type(...), $values)];
};
$calls = [];
foreach (['fast', 'global'] as $direction) {
    $calls[] = [$direction, 'sku_list', 'querySkuList', ['dwSkuId' => [65412613, 65412626, 65412648], 'pageSize' => 3]];
    $calls[] = [$direction, 'spu_nested', 'querySpuList', ['dwSpuId' => [10903], 'pageSize' => 1, 'querySku' => true]];
    foreach (['absent' => null, 'false' => false, 'true' => true] as $variant => $hasPrice) {
        $business = ['dwSkuIds' => [65412613, 65412626, 65412648]];
        if ($hasPrice !== null) {
            $business['hasPrice'] = $hasPrice;
        }
        $calls[] = [$direction, 'prices_'.$variant, 'querySkuBidPrice', $business];
    }
    foreach (['PRODUCT_ON', 'PRODUCT_OFF'] as $status) {
        $calls[] = [$direction, 'legacy_status_'.$status, 'querySkuList', ['dwSkuId' => [65412613, 65412626, 65412648], 'pageSize' => 3, 'distStatus' => $status]];
    }
}
foreach ([200, 201] as $count) {
    $calls[] = ['global', 'price_batch_'.$count, 'querySkuBidPrice', ['dwSkuIds' => range(65412561, 65412560 + $count), 'hasPrice' => true]];
}
if (getenv('POIZON_SPIKE03_SNAPSHOT') === '1') {
    $calls = [];
    foreach (['fast', 'global'] as $direction) {
        $calls[] = [$direction, 'snapshot_sku', 'querySkuList', ['dwSkuId' => [65412613, 65412626, 65412648], 'pageSize' => 3]];
        $calls[] = [$direction, 'snapshot_prices', 'querySkuBidPrice', ['dwSkuIds' => [65412613, 65412626, 65412648], 'hasPrice' => true]];
    }
}
$offProbe = getenv('POIZON_SPIKE03_OFF_PROBE');
if (in_array($offProbe, ['querySkuList', 'querySpuList'], true)) {
    $calls = [['global', 'off_probe_'.$offProbe, $offProbe, ['distStatus' => 'PRODUCT_OFF', 'pageSize' => 1, 'startId' => 0]]];
}
if (getenv('POIZON_SPIKE03_SUPPLEMENT_PROBE') === '1') {
    $calls = [];
    foreach (['fast', 'global'] as $direction) {
        $calls[] = [$direction, 'supplement_probe', 'querySkuBidPrice', ['dwSkuIds' => [65412613, 65412626, 65412648]]];
    }
}
foreach ($calls as [$direction, $case, $operation, $business]) {
    $safe = ['checked_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format(DATE_ATOM),
        'connection' => $direction === 'fast' ? 'channel-A' : 'channel-B', 'case' => $case, 'operation' => $operation, 'business' => $business];
    try {
        $result = $poizonRead($direction, $operation, $business);
        $body = $result['body'];
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $field = $operation === 'querySkuBidPrice' ? 'items' : ($operation === 'querySpuList' ? 'spuList' : 'skuList');
        $rows = is_array($data[$field] ?? null) ? $data[$field] : [];
        $safe += ['http' => $result['http'], 'code' => $body['code'] ?? null, 'attempts' => $result['attempts'],
            'data_fields' => array_keys($data), 'count' => count($rows), 'records' => array_map($sample, $rows)];
        if ($operation === 'querySpuList') {
            $safe['nested'] = [];
            foreach ($rows as $spu) {
                foreach ($spu['skuList'] ?? [] as $sku) {
                    if (in_array($sku['dwSkuId'] ?? null, [65412613, 65412626, 65412648], true)) {
                        $safe['nested'][] = $sample($sku);
                    }
                }
            }
        }
        echo json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        if ($result['http'] >= 500 || in_array($result['http'], [401, 403, 429], true)) {
            break;
        }
    } catch (Throwable $exception) {
        $safe['exception_class'] = get_class($exception);
        echo json_encode($safe, JSON_THROW_ON_ERROR)."\n";
        break;
    }
    usleep(500000);
}
