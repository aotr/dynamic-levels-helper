<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TOON Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the TOON (Token-Optimized Object Notation)
    | encoding/decoding service integration.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | TOON encoding/decoding caching settings to improve performance and reduce
    | computational overhead for frequently used data.
    |
    */

    'cache' => [
        'enabled' => env('TOON_CACHE_ENABLED', true),
        'ttl' => [
            'default' => env('TOON_CACHE_TTL_DEFAULT', 3600), // 1 hour
            'encode' => env('TOON_CACHE_TTL_ENCODE', 7200), // 2 hours
            'decode' => env('TOON_CACHE_TTL_DECODE', 7200), // 2 hours
        ],
        'prefix' => env('TOON_CACHE_PREFIX', 'toon_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Logging settings for TOON encoding/decoding operations.
    |
    */

    'logging' => [
        'enabled' => env('TOON_LOGGING_ENABLED', false),
        'channel' => env('TOON_LOG_CHANNEL', 'toon'),
        'level' => env('TOON_LOG_LEVEL', 'info'),
        'log_operations' => env('TOON_LOG_OPERATIONS', true),
        'log_errors' => env('TOON_LOG_ERRORS', true),
        'log_performance' => env('TOON_LOG_PERFORMANCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encoding Configuration
    |--------------------------------------------------------------------------
    |
    | Default encoding options for TOON format conversion.
    |
    */

    'encoding' => [
        'default_options' => [
            'compress' => env('TOON_ENCODING_COMPRESS', true),
            'optimize' => env('TOON_ENCODING_OPTIMIZE', true),
        ],
        'batch_size' => env('TOON_BATCH_SIZE', 100), // Maximum items per batch operation
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance-related settings for TOON operations.
    |
    */

    'performance' => [
        'max_string_length' => env('TOON_MAX_STRING_LENGTH', 1000000), // 1MB
        'enable_compression_stats' => env('TOON_ENABLE_COMPRESSION_STATS', true),
        'track_performance' => env('TOON_TRACK_PERFORMANCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific TOON features.
    |
    */

    'features' => [
        'batch_operations' => env('TOON_FEATURE_BATCH_OPERATIONS', true),
        'caching' => env('TOON_FEATURE_CACHING', true),
        'validation' => env('TOON_FEATURE_VALIDATION', true),
        'compression_stats' => env('TOON_FEATURE_COMPRESSION_STATS', true),
    ],
];