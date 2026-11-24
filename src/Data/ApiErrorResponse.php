<?php

namespace Muscobytes\Poizon\Data;

final readonly class ApiErrorResponse extends ApiResponse
{
    public function __construct(public ?Record $data, ResponseMetadata $metadata, ?string $message = null, int $code = 200)
    {
        parent::__construct($code, $message, $metadata);
    }
}
