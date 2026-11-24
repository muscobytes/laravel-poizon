<?php

namespace Muscobytes\Poizon\Data;

use Illuminate\Support\Str;
use Muscobytes\Poizon\Exceptions\InvalidRequestException;

final readonly class CallContext
{
    public function __construct(#[\SensitiveParameter] public ?string $correlationId = null, #[\SensitiveParameter] public ?string $parentOperationId = null)
    {
        foreach ([$correlationId, $parentOperationId] as $id) {
            if ($id !== null && ! Str::isUuid($id)) {
                throw new InvalidRequestException;
            }
        }
    }
}
