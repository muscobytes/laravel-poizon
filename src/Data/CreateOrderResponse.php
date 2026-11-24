<?php

namespace Muscobytes\Poizon\Data;

final readonly class CreateOrderResponse extends ApiResponse
{
    public function __construct(public CreateOrderData $data, ResponseMetadata $metadata, ?string $message = null, int $code = 200)
    {
        parent::__construct($code, $message, $metadata);
    }
}
