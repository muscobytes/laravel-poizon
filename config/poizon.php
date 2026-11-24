<?php

return [
    'http' => [
        'retry' => [
            'enabled' => env('POIZON_HTTP_RETRY_ENABLED', false),
            'max_retries' => env('POIZON_HTTP_RETRY_COUNT', 2),
            'delays_ms' => [500, 1000],
        ],
    ],
    'open_platform' => [
        'app_key' => env('POIZON_OPEN_APP_KEY'),
        'app_secret' => env('POIZON_OPEN_APP_SECRET'),
        'base_url' => env('POIZON_OPEN_BASE_URL', 'https://open.poizon.com'),
        'callback_url' => env('POIZON_OPEN_CALLBACK_URL'),
        'language' => env('POIZON_OPEN_LANGUAGE', 'en'),
        'redirect_uri' => env('POIZON_OPEN_REDIRECT_URI'),
        'time_zone' => env('POIZON_OPEN_TIME_ZONE', 'Asia/Shanghai'),
    ],
];
