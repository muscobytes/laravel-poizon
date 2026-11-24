<?php

namespace Muscobytes\Poizon\Exceptions;

use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Enums\AuthorizationReason;

class AuthorizationException extends PoizonException
{
    public function __construct(public readonly AuthorizationReason $reason, ErrorContext $context = new ErrorContext)
    {
        parent::__construct('Poizon authorization is required.', $context);
    }
}
