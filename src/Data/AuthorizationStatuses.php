<?php

namespace Muscobytes\Poizon\Data;

final readonly class AuthorizationStatuses
{
    /** @param array<string, AuthorizationStatus> $items */
    public function __construct(public array $items) {}
}
