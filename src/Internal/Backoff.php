<?php

namespace Muscobytes\Poizon\Internal;

use Illuminate\Support\Facades\Cache;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Exceptions\RateLimitException;
use Muscobytes\Poizon\Exceptions\UnexpectedIntegrationException;

final class Backoff
{
    private function key(): string
    {
        return 'poizon-open-backoff:'.hash('sha256', (string) config('poizon.open_platform.app_key'));
    }

    public function check(string $operation = 'unknown'): void
    {
        try {
            $until = Cache::get($this->key());
        } catch (\Throwable) {
            throw new UnexpectedIntegrationException(new ErrorContext($operation));
        }
        if (is_int($until) && $until > now()->timestamp) {
            throw new RateLimitException(new ErrorContext(operation: $operation, retryAfter: $until - now()->timestamp));
        }
    }

    public function remember(?string $header): ?int
    {
        if ($header === null || strlen($header) > 64) {
            return null;
        }
        $value = trim($header);
        if (preg_match('/^\d{1,9}$/D', $value)) {
            $delay = (int) $value;
        } else {
            $date = \DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));
            if ($date === false) {
                return null;
            }
            $delay = max(0, $date->getTimestamp() - now()->timestamp);
        }
        if ($delay > 0) {
            try {
                Cache::put($this->key(), now()->timestamp + $delay, $delay);
            } catch (\Throwable) {
                throw new UnexpectedIntegrationException;
            }
        }

        return $delay;
    }
}
