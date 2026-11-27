<?php

// Stream after 01-live-bootstrap.php, removing this opening tag.
$summarize = static function (string $operation, array $business, array $response): array {
    $body = $response['body'];
    $data = is_array($body['data'] ?? null) ? $body['data'] : [];
    $list = $operation === 'querySpuList' ? 'spuList' : 'skuList';
    $rows = is_array($data[$list] ?? null) ? $data[$list] : [];
    $meta = [];
    foreach (['lastId', 'hasMore', 'total'] as $key) {
        $value = $data[$key] ?? null;
        $meta[$key] = ['present' => array_key_exists($key, $data), 'type' => get_debug_type($value)];
        if (is_int($value) || is_bool($value) || $value === null || (is_string($value) && preg_match('/^\d+$/D', $value))) {
            $meta[$key]['value'] = $value;
        }
    }
    $ids = [];
    foreach ($rows as $row) {
        $safe = [];
        foreach (['id', 'dwSpuId', 'dwSkuId', 'modifyTime'] as $key) {
            $value = $row[$key] ?? null;
            if ((is_int($value) || is_string($value)) && preg_match('/^\d+$/D', (string) $value)) {
                $safe[$key] = (string) $value;
            }
        }
        $ids[] = $safe;
    }

    return ['operation' => $operation, 'business' => $business, 'http' => $response['http'], 'code' => is_int($body['code'] ?? null) ? $body['code'] : null, 'attempts' => $response['attempts'], 'count' => count($rows), 'metadata' => $meta, 'ids' => $ids];
};

foreach (['querySpuList' => [1, 199, 200, 201, 500, 2000, null], 'querySkuList' => [1, 1999, 2000, 2001, null]] as $operation => $sizes) {
    foreach ($sizes as $size) {
        $business = ['dwSpuId' => [10903], 'startId' => 0];
        if ($size !== null) {
            $business['pageSize'] = $size;
        }
        try {
            $response = $poizonRead('global', $operation, $business);
            echo json_encode($summarize($operation, $business, $response), JSON_THROW_ON_ERROR)."\n";
            if ($response['http'] === 401 || $response['http'] === 403 || $response['http'] >= 500) {
                exit(2);
            }
        } catch (Throwable $exception) {
            echo json_encode(['operation' => $operation, 'business' => $business, 'failure' => get_class($exception)], JSON_THROW_ON_ERROR)."\n";
            exit(2);
        }
        usleep(300000);
    }
}
