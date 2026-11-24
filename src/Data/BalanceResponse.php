<?php

namespace Muscobytes\Poizon\Data;

final readonly class BalanceResponse extends ApiResponse
{
    public function __construct(public BalanceData $data, ResponseMetadata $metadata, ?string $message = null, int $code = 200)
    {
        parent::__construct($code, $message, $metadata);
    }
}
