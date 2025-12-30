<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Toon API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the Toon Smart Home API integration.
    |
    */

    'api' => [
        'client_id' => env('TOON_CLIENT_ID'),
        'client_secret' => env('TOON_CLIENT_SECRET'),
        'username' => env('TOON_USERNAME'),
        'password' => env('TOON_PASSWORD'),
        'agreement_id' => env('TOON_AGREEMENT_ID'),
        'base_url' => env('TOON_BASE_URL', 'https://api.toon.eu'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Toon API response caching settings to improve performance and reduce
    | API calls. Adjust cache TTL values as needed.
    |
    */

    'cache' => [
        'enabled' => env('TOON_CACHE_ENABLED', true),
        'ttl' => [
            'thermostat' => env('TOON_CACHE_TTL_THERMOSTAT', 300), // 5 minutes
            'energy' => env('TOON_CACHE_TTL_ENERGY', 600), // 10 minutes
            'devices' => env('TOON_CACHE_TTL_DEVICES', 300), // 5 minutes
            'programs' => env('TOON_CACHE_TTL_PROGRAMS', 3600), // 1 hour
        ],
        'prefix' => env('TOON_CACHE_PREFIX', 'toon_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Logging settings for Toon API requests and responses.
    |
    */

    'logging' => [
        'enabled' => env('TOON_LOGGING_ENABLED', false),
        'channel' => env('TOON_LOG_CHANNEL', 'toon'),
        'level' => env('TOON_LOG_LEVEL', 'info'),
        'log_requests' => env('TOON_LOG_REQUESTS', true),
        'log_responses' => env('TOON_LOG_RESPONSES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    |
    | HTTP request settings for Toon API calls.
    |
    */

    'timeout' => env('TOON_TIMEOUT', 30),
    'retry' => [
        'enabled' => env('TOON_RETRY_ENABLED', true),
        'attempts' => env('TOON_RETRY_ATTEMPTS', 3),
        'delay' => env('TOON_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific Toon features.
    |
    */

    'features' => [
        'thermostat' => env('TOON_FEATURE_THERMOSTAT', true),
        'energy_monitoring' => env('TOON_FEATURE_ENERGY_MONITORING', true),
        'smart_plugs' => env('TOON_FEATURE_SMART_PLUGS', true),
        'solar_data' => env('TOON_FEATURE_SOLAR_DATA', true),
    ],
];