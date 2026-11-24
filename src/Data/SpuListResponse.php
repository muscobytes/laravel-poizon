<?php

namespace Muscobytes\Poizon\Data;

final readonly class SpuListResponse extends ApiResponse
{
    public function __construct(public SpuListData $data, ResponseMetadata $metadata, ?string $message = null, int $code = 200)
    {
        parent::__construct($code, $message, $metadata);
    }
}
