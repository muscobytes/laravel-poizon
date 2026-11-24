<?php

namespace Muscobytes\Poizon\Internal;

use Muscobytes\Poizon\Exceptions\ProtocolException;

final class Hydrator
{
    public static function record(string $class, #[\SensitiveParameter] array $values, array $schema): object
    {
        $arguments = [];
        foreach ($schema as $field => $descriptor) {
            $required = str_starts_with($descriptor, '!');
            $type = ltrim($descriptor, '!');
            if (! array_key_exists($field, $values)) {
                if ($required) {
                    throw new ProtocolException(field: $field);
                }

                continue;
            }
            $value = $values[$field];
            if ($value === null && ! $required) {
                $arguments[$field] = null;

                continue;
            }
            try {
                $arguments[$field] = self::value($value, $type);
            } catch (ProtocolException $e) {
                throw new ProtocolException(field: $e->field ?? $field);
            }
        }
        $arguments['extraFields'] = array_diff_key($values, $schema);
        $arguments['presentFields'] = array_keys($values);

        return new $class(...$arguments);
    }

    private static function value(#[\SensitiveParameter] mixed $value, string $type): mixed
    {
        if (str_starts_with($type, 'list:')) {
            if (! is_array($value) || ! array_is_list($value)) {
                throw new ProtocolException;
            }
            $class = 'Muscobytes\\Poizon\\Data\\'.substr($type, 5);

            $items = [];
            foreach ($value as $item) {
                if (! is_array($item)) {
                    throw new ProtocolException;
                }
                $items[] = $class::fromArray($item);
            }

            return $items;
        }
        $valid = match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'bool' => is_bool($value),
            'id' => (is_int($value) || is_string($value)) && preg_match('/^\d+$/D', (string) $value),
            'decimal' => (is_int($value) || is_string($value)) && preg_match('/^-?\d{1,30}(?:\.\d{1,12})?$/D', (string) $value),
            'money_decimal' => is_string($value) && preg_match('/^-?\d{1,30}(?:\.\d{1,12})?$/D', $value),
            'currency' => is_string($value) && preg_match('/^[A-Z]{3}$/D', $value),
            default => null,
        };
        if ($valid === null) {
            if (! is_array($value)) {
                throw new ProtocolException;
            }
            $class = 'Muscobytes\\Poizon\\Data\\'.$type;

            return $class::fromArray($value);
        }
        if (! $valid) {
            throw new ProtocolException;
        }

        return in_array($type, ['id', 'decimal'], true) ? (string) $value : $value;
    }
}
