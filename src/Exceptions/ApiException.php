<?php

namespace Muscobytes\Poizon\Exceptions;

use Muscobytes\Poizon\Data\ApiErrorResponse;
use Muscobytes\Poizon\Data\ErrorContext;

class ApiException extends PoizonException
{
    public function __construct(#[\SensitiveParameter] private readonly ApiErrorResponse $response, ErrorContext $context)
    {
        parent::__construct('Poizon rejected the operation.', $context);
    }

    public function response(): ApiErrorResponse
    {
        return $this->response;
    }
}
