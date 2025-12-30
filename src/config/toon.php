<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Toon API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the Toon smart home integration.
    | This includes API credentials and connection settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Toon API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Toon API credentials. You can obtain these from the Toon developer
    | portal after registering your application.
    |
    */
    'client_id' => env('TOON_CLIENT_ID'),
    'client_secret' => env('TOON_CLIENT_SECRET'),
    'redirect_uri' => env('TOON_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Toon API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Toon API. This should not normally need to be changed.
    |
    */
    'base_url' => env('TOON_BASE_URL', 'https://api.toon.eu/toon/v3'),

    /*
    |--------------------------------------------------------------------------
    | Default Agreement ID
    |--------------------------------------------------------------------------
    |
    | Your default Toon agreement/contract ID. This can be found in your
    | Toon account settings.
    |
    */
    'agreement_id' => env('TOON_AGREEMENT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for caching Toon API responses to improve performance
    | and reduce API calls.
    |
    */
    'cache' => [
        'enabled' => env('TOON_CACHE_ENABLED', true),
        'ttl' => env('TOON_CACHE_TTL', 300), // 5 minutes
        'key_prefix' => env('TOON_CACHE_PREFIX', 'toon_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP request timeout in seconds for Toon API calls.
    |
    */
    'timeout' => env('TOON_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging for Toon API requests and responses.
    |
    */
    'logging' => [
        'enabled' => env('TOON_LOGGING_ENABLED', true),
        'channel' => env('TOON_LOGGING_CHANNEL', 'toon'),
    ],
];