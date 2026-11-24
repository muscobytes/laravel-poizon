<?php

namespace Muscobytes\Poizon\Internal;

use Muscobytes\Poizon\Exceptions\InvalidRequestException;

final class InputValidator
{
    public static function parameters(#[\SensitiveParameter] array $parameters, array $schema, array $required = []): array
    {
        if (array_diff($required, array_keys($parameters)) !== []) {
            throw new InvalidRequestException;
        }
        foreach ($parameters as $key => $value) {
            $type = $schema[$key] ?? null;
            if ($type === null || $value === null) {
                throw new InvalidRequestException;
            }
            if (in_array($type, ['longs', 'strings', 'empty_longs', 'empty_strings'], true)) {
                $emptyAllowed = str_starts_with($type, 'empty_');
                if (! is_array($value) || ! array_is_list($value) || (! $emptyAllowed && $value === []) || (($emptyAllowed || $key === 'dwSkuIds') && count($value) > 200)) {
                    throw new InvalidRequestException;
                }
                $items = [];
                foreach ($value as $item) {
                    $items[] = str_ends_with($type, 'longs') ? self::long($item) : self::string($item);
                }
                $parameters[$key] = $items;
            } elseif ($type === 'string') {
                $parameters[$key] = self::string($value);
            } elseif ($type === 'bool') {
                if (! is_bool($value)) {
                    throw new InvalidRequestException;
                }
            } elseif ($type === 'long') {
                $parameters[$key] = self::long($value);
            } else {
                if (! is_int($value) || $value < 1 || $value > match ($type) {
                    'page' => 2000, 'order_page' => 200, default => PHP_INT_MAX
                }) {
                    throw new InvalidRequestException;
                }
            }
        }

        return $parameters;
    }

    public static function long(#[\SensitiveParameter] mixed $value): int
    {
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^\d{1,19}$/D', (string) $value)) {
            throw new InvalidRequestException;
        }
        $digits = ltrim((string) $value, '0');
        if (strlen($digits) > strlen((string) PHP_INT_MAX) || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) > 0)) {
            throw new InvalidRequestException;
        }

        return (int) $value;
    }

    public static function string(#[\SensitiveParameter] mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidRequestException;
        }

        return $value;
    }
}
