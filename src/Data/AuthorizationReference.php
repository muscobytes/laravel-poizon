<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Exceptions\InvalidRequestException;
use Muscobytes\Poizon\Support\ConnectionName;

final readonly class AuthorizationReference
{
    private function __construct(public ?string $connection, public ?int $authorizationId) {}

    public static function forConnection(#[\SensitiveParameter] string $name): self
    {
        ConnectionName::validate($name);

        return new self($name, null);
    }

    public static function forAuthorization(int $id): self
    {
        if ($id < 1) {
            throw new InvalidRequestException;
        }

        return new self(null, $id);
    }
}
