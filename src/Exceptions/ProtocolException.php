<?php

namespace Muscobytes\Poizon\Exceptions;

use Muscobytes\Poizon\Data\ErrorContext;

class ProtocolException extends PoizonException
{
    public function __construct(ErrorContext $context = new ErrorContext, ?PoizonException $previous = null, public readonly ?string $field = null, public readonly ?string $actualType = null)
    {
        parent::__construct('Poizon returned an invalid protocol response.', $context, $previous);
    }
}
