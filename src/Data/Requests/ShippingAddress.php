<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Internal\InputValidator;

final readonly class ShippingAddress
{
    public function __construct(#[\SensitiveParameter] public string $name, #[\SensitiveParameter] public string $email, #[\SensitiveParameter] public string $phone, #[\SensitiveParameter] public string $province,
        #[\SensitiveParameter] public string $city, #[\SensitiveParameter] public string $district, #[\SensitiveParameter] public string $detailAddress)
    {
        foreach (get_object_vars($this) as $value) {
            InputValidator::string($value);
        }
    }

    public function __debugInfo(): array
    {
        return ['address' => '[redacted]'];
    }
}
