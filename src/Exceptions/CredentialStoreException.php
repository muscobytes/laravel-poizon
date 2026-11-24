<?php

namespace Muscobytes\Poizon\Exceptions;

use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Enums\CredentialFailure;

class CredentialStoreException extends PoizonException
{
    public function __construct(public readonly CredentialFailure $reason, ErrorContext $context = new ErrorContext, public readonly ?string $sqlState = null)
    {
        parent::__construct('Poizon credential storage is unavailable.', $context);
    }
}
