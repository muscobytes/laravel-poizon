<?php

namespace Muscobytes\Poizon\Events;

use Muscobytes\Poizon\Data\ErrorContext;

final readonly class OAuthFailed
{
    public function __construct(public ErrorContext $context, public string $errorType, public ?string $field = null,
        public ?string $actualType = null, public ?string $storageReason = null, public ?string $sqlState = null) {}
}
