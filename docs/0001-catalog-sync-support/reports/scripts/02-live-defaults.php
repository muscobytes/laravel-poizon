<?php

// Stream after 01-live-bootstrap.php, removing this opening tag. Exactly four reads.
foreach (['querySpuList' => ['filter' => range(10903, 11102), 'size' => 200], 'querySkuList' => ['filter' => range(10903, 11002), 'size' => 2000]] as $operation => $case) {
    foreach ([$case['size'], null] as $size) {
        $business = ['dwSpuId' => $case['filter'], 'startId' => 0];
        if ($size !== null) {
            $business['pageSize'] = $size;
        }
        try {
            $response = $poizonRead('global', $operation, $business);
            $body = $response['body'];
            $data = is_array($body['data'] ?? null) ? $body['data'] : [];
            $list = $operation === 'querySpuList' ? 'spuList' : 'skuList';
            $rows = is_array($data[$list] ?? null) ? $data[$list] : [];
            $ids = [];
            foreach ($rows as $row) {
                $safe = [];
                foreach (['id', 'dwSpuId', 'dwSkuId'] as $key) {
                    $value = $row[$key] ?? null;
                    if ((is_int($value) || is_string($value)) && preg_match('/^\d+$/D', (string) $value)) {
                        $safe[$key] = (string) $value;
                    }
                }
                $ids[] = $safe;
            }
            echo json_encode(['operation' => $operation, 'business' => $business, 'http' => $response['http'], 'code' => is_int($body['code'] ?? null) ? $body['code'] : null, 'attempts' => $response['attempts'], 'count' => count($rows), 'total' => is_int($data['total'] ?? null) ? $data['total'] : null, 'lastIdPresent' => array_key_exists('lastId', $data), 'hasMorePresent' => array_key_exists('hasMore', $data), 'ids' => $ids], JSON_THROW_ON_ERROR)."\n";
            if ($response['http'] !== 200 || ($body['code'] ?? null) !== 200) {
                exit(2);
            }
        } catch (Throwable $exception) {
            echo json_encode(['operation' => $operation, 'business' => $business, 'failure' => get_class($exception)], JSON_THROW_ON_ERROR)."\n";
            exit(2);
        }
        usleep(300000);
    }
}
