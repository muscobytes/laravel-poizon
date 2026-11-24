<?php

namespace Muscobytes\Poizon\Exceptions;

use Muscobytes\Poizon\Data\ErrorContext;

class HttpException extends PoizonException
{
    public function __construct(ErrorContext $context = new ErrorContext, ?PoizonException $previous = null)
    {
        parent::__construct('Poizon returned an unsuccessful HTTP status.', $context, $previous);
    }
}
