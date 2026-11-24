<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Exceptions\InvalidRequestException;
use Muscobytes\Poizon\Internal\InputValidator;

final readonly class OrderProduct
{
    public function __construct(#[\SensitiveParameter] public string $dwSpuId, #[\SensitiveParameter] public string $dwSkuId, #[\SensitiveParameter] public int $quantity,
        #[\SensitiveParameter] public string $price, #[\SensitiveParameter] public ?string $distSpuId = null, #[\SensitiveParameter] public ?string $distSkuId = null)
    {
        InputValidator::long($dwSpuId);
        InputValidator::long($dwSkuId);
        if ($quantity < 1 || InputValidator::long($price) < 1) {
            throw new InvalidRequestException;
        }
        foreach ([$distSpuId, $distSkuId] as $id) {
            if ($id !== null) {
                InputValidator::string($id);
            }
        }
    }

    public function parameters(): array
    {
        return array_filter(['dwSpuId' => InputValidator::long($this->dwSpuId), 'dwSkuId' => InputValidator::long($this->dwSkuId),
            'quantity' => $this->quantity, 'price' => InputValidator::long($this->price), 'distSpuId' => $this->distSpuId, 'distSkuId' => $this->distSkuId], static fn ($value) => $value !== null);
    }
}
