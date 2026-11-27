<?php

// Offline evidence only: the SDK validator does not establish upstream limits.
require dirname(__DIR__, 4).'/vendor/autoload.php';

use Muscobytes\Poizon\Data\Requests\SkuListQuery;
use Muscobytes\Poizon\Data\Requests\SpuListQuery;
use Muscobytes\Poizon\Data\SkuListData;
use Muscobytes\Poizon\Data\SpuListData;
use Muscobytes\Poizon\Exceptions\InvalidRequestException;

foreach ([SpuListQuery::class, SkuListQuery::class] as $query) {
    foreach ([1, 199, 200, 201, 500, 1999, 2000, 2001] as $size) {
        foreach (['constructor', 'fromArray'] as $path) {
            try {
                $result = $path === 'constructor' ? new $query(pageSize: $size) : $query::fromArray(['pageSize' => $size]);
                if ($size > 2000 || $result->parameters()['pageSize'] !== $size) {
                    throw new RuntimeException('Unexpected pagination validation result.');
                }
                $outcome = 'accepted';
            } catch (InvalidRequestException) {
                if ($size !== 2001) {
                    throw new RuntimeException('Unexpected pagination rejection.');
                }
                $outcome = 'InvalidRequestException';
            }
            echo json_encode(['query' => $query, 'path' => $path, 'pageSize' => $size, 'outcome' => $outcome], JSON_THROW_ON_ERROR)."\n";
        }
    }
}

foreach ([SpuListData::class => 'spuList', SkuListData::class => 'skuList'] as $class => $list) {
    $missing = $class::fromArray([$list => []]);
    $null = $class::fromArray([$list => [], 'lastId' => null, 'hasMore' => null, 'total' => null]);
    $present = $class::fromArray([$list => [], 'lastId' => '9007199254740993', 'hasMore' => false, 'total' => 0]);
    if ($missing->has('lastId') || ! $null->has('lastId') || $null->lastId !== null || $present->lastId !== '9007199254740993' || $present->hasMore !== false || $present->total !== 0) {
        throw new RuntimeException('Pagination metadata presence or precision changed.');
    }
    echo json_encode(['data' => $class, 'presence' => 'missing and null distinguished', 'largeCursor' => $present->lastId], JSON_THROW_ON_ERROR)."\n";
}
