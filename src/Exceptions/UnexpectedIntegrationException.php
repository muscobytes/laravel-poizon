<?php

namespace Muscobytes\Poizon\Exceptions;

use Muscobytes\Poizon\Data\ErrorContext;

class UnexpectedIntegrationException extends PoizonException
{
    public function __construct(ErrorContext $context = new ErrorContext, ?PoizonException $previous = null)
    {
        parent::__construct('An integration dependency failed unexpectedly.', $context, $previous);
    }
}
