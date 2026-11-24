<?php

namespace Muscobytes\Poizon\Exceptions;

use Muscobytes\Poizon\Data\ErrorContext;

abstract class PoizonException extends \RuntimeException
{
    public function __construct(string $message, public readonly ErrorContext $context = new ErrorContext, ?PoizonException $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function withContext(ErrorContext $context): static
    {
        return match (true) {
            $this instanceof AuthorizationException => new static($this->reason, $context),
            $this instanceof CredentialStoreException => new static($this->reason, $context, $this->sqlState),
            $this instanceof ProtocolException => new static($context, $this, $this->field, $this->actualType),
            $this instanceof ApiException => new static($this->response(), $context),
            default => new static($context, $this),
        };
    }
}
