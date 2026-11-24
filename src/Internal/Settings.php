<?php

namespace Muscobytes\Poizon\Internal;

use DateTimeZone;
use Muscobytes\Poizon\Exceptions\ConfigurationException;

final class Settings
{
    public function credentials(): array
    {
        $key = config('poizon.open_platform.app_key');
        $secret = config('poizon.open_platform.app_secret');
        $baseUrl = config('poizon.open_platform.base_url', 'https://open.poizon.com');
        if (! is_string($key) || $key === '' || ! is_string($secret) || $secret === '' || ! is_string($baseUrl) || rtrim($baseUrl, '/') !== 'https://open.poizon.com') {
            throw new ConfigurationException;
        }

        return ['app_key' => $key, 'app_secret' => $secret];
    }

    public function openPlatform(): array
    {
        $credentials = $this->credentials();
        $language = config('poizon.open_platform.language', 'en');
        $zone = config('poizon.open_platform.time_zone', 'Asia/Shanghai');
        if (! is_string($language) || ! preg_match('/^[a-z]{2}$/D', $language) || ! in_array($zone, DateTimeZone::listIdentifiers(), true)) {
            throw new ConfigurationException;
        }

        return $credentials + ['language' => $language, 'time_zone' => $zone];
    }

    public function redirectUri(): string
    {
        $uri = config('poizon.open_platform.redirect_uri');
        $parts = is_string($uri) ? parse_url($uri) : false;
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || ! filter_var($uri, FILTER_VALIDATE_URL)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ConfigurationException;
        }

        return $uri;
    }
}
