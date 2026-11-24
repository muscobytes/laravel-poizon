<?php

namespace Muscobytes\Poizon\Data;

final readonly class ResponseMetadata
{
    public function __construct(public string $operationId, public int $httpStatus, public int $attempts, public float $durationMs) {}
}
