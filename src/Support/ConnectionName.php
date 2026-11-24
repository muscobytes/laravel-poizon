<?php

namespace Muscobytes\Poizon\Support;

use Muscobytes\Poizon\Exceptions\InvalidRequestException;

final class ConnectionName
{
    public static function valid(mixed $name): bool
    {
        return is_string($name) && preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $name) === 1;
    }

    public static function validate(#[\SensitiveParameter] mixed $name): void
    {
        if (! self::valid($name)) {
            throw new InvalidRequestException;
        }
    }
}
