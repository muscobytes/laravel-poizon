<?php

namespace Muscobytes\Poizon\Data;

abstract readonly class ApiResponse
{
    public function __construct(public int $code, public ?string $message, public ResponseMetadata $metadata) {}
}
