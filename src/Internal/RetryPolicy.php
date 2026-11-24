<?php

namespace Muscobytes\Poizon\Internal;

use Muscobytes\Poizon\Exceptions\ConfigurationException;

final readonly class RetryPolicy
{
    public bool $enabled;

    public int $maxRetries;

    public array $delays;

    public function __construct(mixed $enabled, mixed $maxRetries, mixed $delays)
    {
        if (is_string($maxRetries) && preg_match('/^\d+$/D', $maxRetries) && strlen($maxRetries) < 10) {
            $maxRetries = (int) $maxRetries;
        }
        if (! is_bool($enabled) || ! is_int($maxRetries) || $maxRetries < 0 || $maxRetries === PHP_INT_MAX || ! is_array($delays) || ! array_is_list($delays) || ($maxRetries > 0 && $delays === [])) {
            throw new ConfigurationException;
        }
        foreach ($delays as $delay) {
            if (! is_int($delay) || $delay < 0) {
                throw new ConfigurationException;
            }
        }
        $this->enabled = $enabled;
        $this->maxRetries = $maxRetries;
        $this->delays = $delays;
    }

    public static function configured(): self
    {
        return new self(config('poizon.http.retry.enabled', false), config('poizon.http.retry.max_retries', 2), config('poizon.http.retry.delays_ms', [500, 1000]));
    }

    public function attempts(bool $allowed): int
    {
        return $allowed && $this->enabled ? $this->maxRetries + 1 : 1;
    }

    public function delay(int $retry): int
    {
        return $this->delays[min($retry - 1, count($this->delays) - 1)];
    }
}
