<?php

return [
    'base_url' => env('API_GEO_URL', 'https://api.services.splitfire.fr/geo'),

    'key' => env('API_GEO_KEY'),

    'timeout' => env('API_GEO_TIMEOUT', 8),

    'connect_timeout' => env('API_GEO_CONNECT_TIMEOUT', 2),

    'retry_times' => env('API_GEO_RETRY_TIMES', 1),

    'retry_sleep_ms' => env('API_GEO_RETRY_SLEEP_MS', 1000),

    'proxy_enabled' => env('API_GEO_PROXY_ENABLED', false),

    'proxy_prefix' => env('API_GEO_PROXY_PREFIX', 'api-geo'),

    'proxy_timeout' => env('API_GEO_PROXY_TIMEOUT', 3),

    'proxy_middleware' => ['throttle:60,1'],
];
