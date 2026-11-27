<?php

// Append this body without its opening tag to 01-live-bootstrap.php and stream to production PHP.
$safeRecord = static function (array $record): array {
    $result = ['fields' => array_keys($record), 'selected' => [], 'types' => []];
    foreach (['id', 'dwSpuId', 'distSpuId', 'dwSkuId', 'distSkuId', 'modifyTime', 'minBidPrice', 'fulfillmentMode', 'productDesc', 'title', 'dwSpuTitle', 'distSpuTitle'] as $field) {
        if (array_key_exists($field, $record)) {
            $value = $record[$field];
            $result['types'][$field] = get_debug_type($value);
            if (is_scalar($value) || $value === null) {
                $result['selected'][$field] = is_string($value) ? mb_substr($value, 0, 300) : $value;
            }
        }
    }
    if (isset($record['skuList']) && is_array($record['skuList'])) {
        $result['nested_sku_count'] = count($record['skuList']);
        $result['nested_sku_sample'] = array_intersect_key($record['skuList'][0] ?? [], array_flip(['dwSkuId', 'distSkuId', 'dwSpuId', 'distSpuId', 'fulfillmentMode']));
        $result['nested_sku_fields'] = array_keys($record['skuList'][0] ?? []);
    }
    foreach (['saleAttr', 'sizeInfos'] as $field) {
        if (isset($record[$field]) && is_array($record[$field])) {
            $result[$field.'_count'] = count($record[$field]);
            $result[$field.'_first_fields'] = array_keys($record[$field][0] ?? []);
        }
    }

    return $result;
};
$cases = [
    'querySpuList' => ['dwSpuId' => [10903], 'pageSize' => 1, 'querySku' => true],
    'querySkuList' => ['dwSkuId' => [65412613], 'pageSize' => 1],
    'querySkuDetail' => ['dwSpuId' => 10903, 'dwSkuId' => [65412613]],
    'querySkuBidPrice' => ['dwSkuIds' => [65412613, 65412626], 'hasPrice' => true],
];
foreach (['fast', 'global'] as $direction) {
    foreach ($cases as $operation => $business) {
        try {
            $result = $poizonRead($direction, $operation, $business);
            $data = $result['body']['data'] ?? [];
            $records = $data[match ($operation) {
                'querySpuList' => 'spuList',
                'querySkuBidPrice' => 'items',
                default => 'skuList',
            }] ?? [];
            $safe = [
                'checked_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format(DATE_ATOM),
                'connection' => $direction === 'fast' ? 'channel-A' : 'channel-B',
                'operation' => $operation, 'business' => $business,
                'http' => $result['http'], 'code' => $result['body']['code'] ?? null,
                'attempts' => $result['attempts'], 'data_fields' => is_array($data) ? array_keys($data) : [],
                'count' => is_array($records) ? count($records) : null,
                'records' => is_array($records) ? array_map($safeRecord, array_slice($records, 0, 2)) : [],
            ];
            echo json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
            if ($result['http'] === 429 || in_array($result['http'], [401, 403], true)) {
                exit(2);
            }
        } catch (Throwable $e) {
            echo json_encode(['connection' => $direction === 'fast' ? 'channel-A' : 'channel-B', 'operation' => $operation, 'failure_class' => get_class($e)])."\n";
            exit(2);
        }
    }
}
