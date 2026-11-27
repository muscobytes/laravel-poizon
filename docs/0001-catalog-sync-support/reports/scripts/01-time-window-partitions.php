<?php

// Concatenate after 01-live-bootstrap.php, omitting this opening tag.
try {
    foreach ([
        ['querySpuList', 'spuList', 'dwSpuId', [10903, 10905, 10906], 1787786149000, 1788477364000, 1788477597000],
        ['querySkuList', 'skuList', 'dwSkuId', [849685084, 65412648, 849741572], 1787181665000, 1787181701000, 1787182104000],
    ] as [$operation, $list, $field, $ids, $start, $split, $end]) {
        foreach (['partition_whole' => [$start, $end], 'partition_left' => [$start, $split], 'partition_right' => [$split, $end]] as $name => [$lower, $upper]) {
            $parameters = [$field => $ids, 'pageSize' => 200, 'modifyStartTime' => $lower, 'modifyEndTime' => $upper];
            $result = $poizonRead('global', $operation, $parameters);
            $body = $result['body'];
            $data = $body['data'] ?? [];
            $safe = [
                'observed_at' => gmdate('c'), 'connection' => 'global', 'operation' => $operation,
                'case' => $name, 'parameters' => $parameters, 'http' => $result['http'],
                'code' => $body['code'] ?? null, 'attempts' => $result['attempts'],
                'data_keys' => is_array($data) ? array_keys($data) : null,
                'pagination' => is_array($data) ? array_intersect_key($data, array_flip(['total', 'lastId', 'hasMore'])) : [],
                'records' => [],
            ];
            foreach ((is_array($data) ? ($data[$list] ?? []) : []) as $record) {
                $safe['records'][] = array_intersect_key($record, array_flip(['id', 'dwSpuId', 'dwSkuId', 'modifyTime']));
            }
            echo json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
            if ($result['http'] !== 200 || ($body['code'] ?? null) !== 200) {
                throw new RuntimeException('Stop on unsuccessful partition probe.');
            }
        }
    }
} catch (Throwable $exception) {
    echo json_encode(['stopped' => true, 'error_class' => get_class($exception)]), "\n";
    exit(1);
}
