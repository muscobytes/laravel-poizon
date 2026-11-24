<?php

namespace Muscobytes\Poizon\Data;

final readonly class SkuListResponse extends ApiResponse
{
    public function __construct(public SkuListData $data, ResponseMetadata $metadata, ?string $message = null, int $code = 200)
    {
        parent::__construct($code, $message, $metadata);
    }
}
