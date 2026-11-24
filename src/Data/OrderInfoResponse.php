<?php

namespace Muscobytes\Poizon\Data;

final readonly class OrderInfoResponse extends ApiResponse
{
    public function __construct(public OrderInfoData $data, ResponseMetadata $metadata, ?string $message = null, int $code = 200)
    {
        parent::__construct($code, $message, $metadata);
    }
}
