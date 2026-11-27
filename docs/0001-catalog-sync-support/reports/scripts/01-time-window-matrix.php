<?php

// Concatenate after 01-live-bootstrap.php, omitting this opening tag, then stream to app PHP.
// Only fixed product identifiers and an explicit response allowlist reach stdout.
try {
    foreach ([
        ['querySpuList', 'spuList', 'dwSpuId', 10903, 1787786149000],
        ['querySkuList', 'skuList', 'dwSkuId', 65412648, 1787181701000],
    ] as [$operation, $list, $field, $id, $time]) {
        $day = 86400000;
        $cases = ['unbounded' => []];
        foreach ([-1, 0, 1] as $offset) {
            $cases['lower_'.$offset] = ['modifyStartTime' => $time + $offset, 'modifyEndTime' => $time + $day];
            $cases['upper_'.$offset] = ['modifyStartTime' => $time - $day, 'modifyEndTime' => $time + $offset];
        }
        $cases += [
            'start_only' => ['modifyStartTime' => $time],
            'end_only' => ['modifyEndTime' => $time],
            'start_only_after' => ['modifyStartTime' => $time + 1],
            'end_only_before' => ['modifyEndTime' => $time - 1],
            'equal' => ['modifyStartTime' => $time, 'modifyEndTime' => $time],
            'reversed' => ['modifyStartTime' => $time + 1, 'modifyEndTime' => $time],
            'zero_start' => ['modifyStartTime' => 0, 'modifyEndTime' => $time + 1],
            'whole' => ['modifyStartTime' => $time - 1, 'modifyEndTime' => $time + 1],
            'left' => ['modifyStartTime' => $time - 1, 'modifyEndTime' => $time],
            'right' => ['modifyStartTime' => $time, 'modifyEndTime' => $time + 1],
            'seconds_instead_of_ms' => ['modifyStartTime' => intdiv($time, 1000) - 1, 'modifyEndTime' => intdiv($time, 1000) + 1],
        ];
        foreach ([1, 6, 24, 168] as $hours) {
            $cases['duration_'.$hours.'h'] = ['modifyStartTime' => $time + 1 - $hours * 3600000, 'modifyEndTime' => $time + 1];
        }
        foreach ($cases as $name => $window) {
            if (isset($poizonTimeCases) && ! in_array($name, $poizonTimeCases, true)) {
                continue;
            }
            $parameters = [$field => [$id], 'pageSize' => 200] + $window;
            $result = $poizonRead('global', $operation, $parameters);
            $body = $result['body'];
            $data = $body['data'] ?? [];
            $safe = [
                'observed_at' => gmdate('c'), 'connection' => 'global', 'operation' => $operation,
                'case' => $name, 'parameters' => $parameters, 'control_modify_time' => $time,
                'http' => $result['http'], 'code' => $body['code'] ?? null, 'attempts' => $result['attempts'],
                'data_keys' => is_array($data) ? array_keys($data) : null,
                'pagination' => is_array($data) ? array_intersect_key($data, array_flip(['total', 'lastId', 'hasMore'])) : [],
                'records' => [],
            ];
            foreach ((is_array($data) ? ($data[$list] ?? []) : []) as $record) {
                $safe['records'][] = array_intersect_key($record, array_flip(['id', 'dwSpuId', 'dwSkuId', 'modifyTime']));
            }
            echo json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
            if (in_array($result['http'], [401, 403], true) || $result['http'] >= 500) {
                throw new RuntimeException('Stop on authorization or upstream HTTP failure.');
            }
            if ($result['http'] !== 200 || ($body['code'] ?? null) !== 200) {
                // Business validation failures remain observations; no retry and no success inference.
                continue;
            }
        }
    }
} catch (Throwable $exception) {
    echo json_encode(['stopped' => true, 'error_class' => get_class($exception)]), "\n";
    exit(1);
}
