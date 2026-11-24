<?php

namespace Muscobytes\Poizon\Services;

use Muscobytes\Poizon\Exceptions\ConfigurationException;
use Muscobytes\Poizon\Exceptions\InvalidRequestException;
use SensitiveParameter;

class PoizonOpenPlatformSigner
{
    public function sign(#[SensitiveParameter] array $parameters, #[SensitiveParameter] string $secret): string
    {
        if ($secret === '' || array_key_exists('sign', $parameters) || array_key_exists('app_secret', $parameters)) {
            throw new ConfigurationException;
        }

        ksort($parameters, SORT_STRING);
        $parts = [];
        foreach ($parameters as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $key)) {
                throw new InvalidRequestException;
            }
            if (is_array($value)) {
                // The official client JSON-stringifies objects without null properties.
                if (array_is_list($value)) {
                    $items = [];
                    foreach ($value as $item) {
                        $items[] = is_array($item) && ! array_is_list($item) ? $this->objectValue($item) : $this->scalarValue($item);
                    }
                    $value = implode(',', $items);
                } else {
                    $value = $this->objectValue($value);
                }
            } else {
                $value = $this->scalarValue($value);
            }
            $encoded = strtr(rawurlencode($value), [
                '%20' => '+', '%21' => '!', '%27' => "'", '%28' => '(', '%29' => ')', '%2A' => '*',
            ]);
            $parts[] = $key.'='.$encoded;
        }

        return strtoupper(md5(implode('&', $parts).$secret));
    }

    private function objectValue(#[SensitiveParameter] array $value): string
    {
        try {
            return json_encode($this->jsonValue($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidRequestException;
        }
    }

    private function jsonValue(#[SensitiveParameter] array $value): array|object
    {
        $clean = [];
        foreach ($value as $key => $item) {
            if ($item !== null || array_is_list($value)) {
                if (is_array($item)) {
                    $item = $this->jsonValue($item);
                } elseif ($item !== null) {
                    $this->scalarValue($item);
                }
                $clean[$key] = $item;
            }
        }

        return array_is_list($value) ? $clean : (object) $clean;
    }

    private function scalarValue(#[SensitiveParameter] mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_bool($value)) {
            throw new InvalidRequestException;
        }

        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }
}
