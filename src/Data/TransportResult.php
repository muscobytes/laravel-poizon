<?php

namespace Muscobytes\Poizon\Data;

use Illuminate\Http\Client\Response;

final readonly class TransportResult
{
    public function __construct(public Response $response, public int $attempts) {}

    public function __debugInfo(): array
    {
        return ['attempts' => $this->attempts];
    }
}
