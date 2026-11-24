<?php

namespace Muscobytes\Poizon\Data;

final readonly class LogisticsResponse extends ApiResponse
{
    public function __construct(public LogisticsData $data, ResponseMetadata $metadata, ?string $message = null, int $code = 200)
    {
        parent::__construct($code, $message, $metadata);
    }
}
