<?php

// Stream after 01-live-bootstrap.php, removing this opening tag. Maximum 32 reads.
foreach (['querySpuList' => ['filter' => range(10903, 10910), 'sizes' => [2, 3]], 'querySkuList' => ['filter' => [10903], 'sizes' => [5, 7]]] as $operation => $case) {
    foreach ($case['sizes'] as $size) {
        $cursor = 0;
        $seen = [];
        for ($page = 1; $page <= 8; $page++) {
            $business = ['dwSpuId' => $case['filter'], 'startId' => $cursor, 'pageSize' => $size];
            try {
                $response = $poizonRead('global', $operation, $business);
                $body = $response['body'];
                $data = is_array($body['data'] ?? null) ? $body['data'] : [];
                $list = $operation === 'querySpuList' ? 'spuList' : 'skuList';
                $rows = is_array($data[$list] ?? null) ? $data[$list] : [];
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
                $summary = ['operation' => $operation, 'business' => $business, 'page' => $page, 'http' => $response['http'], 'code' => is_int($body['code'] ?? null) ? $body['code'] : null, 'attempts' => $response['attempts'], 'count' => count($rows), 'total' => is_int($data['total'] ?? null) ? $data['total'] : null, 'lastIdPresent' => array_key_exists('lastId', $data), 'hasMorePresent' => array_key_exists('hasMore', $data), 'ids' => $ids];
                echo json_encode($summary, JSON_THROW_ON_ERROR)."\n";
                if ($summary['http'] !== 200 || $summary['code'] !== 200) {
                    exit(2);
                }
                if ($rows === []) {
                    break;
                }
                foreach ($ids as $row) {
                    if (! isset($row['id']) || (int) $row['id'] <= $cursor || isset($seen[$row['id']])) {
                        throw new RuntimeException('Cursor did not advance or record repeated.');
                    }
                    $seen[$row['id']] = true;
                }
                $cursor = max(array_map(static fn ($row) => (int) $row['id'], $ids));
                if ($page === 8) {
                    throw new RuntimeException('Bounded walk did not reach an empty page.');
                }
            } catch (Throwable $exception) {
                echo json_encode(['operation' => $operation, 'business' => $business, 'failure' => get_class($exception)], JSON_THROW_ON_ERROR)."\n";
                exit(2);
            }
            usleep(300000);
        }
    }
}
