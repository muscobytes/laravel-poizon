<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Internal\InputValidator;

final readonly class CreateOrderRequest
{
    public function __construct(
        #[\SensitiveParameter] public ShippingAddress $shippingAddress,
        #[\SensitiveParameter] public OrderProduct $product,
        #[\SensitiveParameter] public ?string $requestId = null,
    ) {
        if ($requestId !== null) {
            InputValidator::string($requestId);
        }
    }

    public function parameters(): array
    {
        return array_filter(['requestId' => $this->requestId, 'shippingAddress' => get_object_vars($this->shippingAddress),
            'product' => $this->product->parameters()], static fn ($value) => $value !== null);
    }
}
