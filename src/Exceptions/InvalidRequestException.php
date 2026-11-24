<?php

namespace Muscobytes\Poizon\Exceptions;

use Muscobytes\Poizon\Data\ErrorContext;

class InvalidRequestException extends PoizonException
{
    public function __construct(ErrorContext $context = new ErrorContext, ?PoizonException $previous = null)
    {
        parent::__construct('Invalid Poizon request.', $context, $previous);
    }
}
