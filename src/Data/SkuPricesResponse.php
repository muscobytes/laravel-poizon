<?php

namespace Muscobytes\Poizon\Data;

final readonly class SkuPricesResponse extends ApiResponse
{
    public function __construct(public SkuPriceData $data, ResponseMetadata $metadata, ?string $message = null, int $code = 200)
    {
        parent::__construct($code, $message, $metadata);
    }
}
