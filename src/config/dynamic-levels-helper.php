<?php

return [
    'basic_auth_username' => env('BASIC_AUTH_USERNAME'),
    'basic_auth_password' => env('BASIC_AUTH_PASSWORD'),
    'db_connection_for_db_service' => env('DB_CONNECTION_FOR_DB_SERVICE'),

    // Enhanced DBService Configuration
        /*
    |--------------------------------------------------------------------------
    | Enhanced Database Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the enhanced database service with singleton pattern,
    | connection pooling, and advanced logging capabilities.
    |
    */
    'enhanced_db_service' => [
        // Default database connection to use
        'default_connection' => env('ENHANCED_DB_SERVICE_CONNECTION', env('DB_CONNECTION', 'mysql')),

        // Logging configuration
        'logging' => [
            'enabled' => env('ENHANCED_DB_SERVICE_LOGGING_ENABLED', true),
            'channel' => env('ENHANCED_DB_SERVICE_LOGGING_CHANNEL', 'stack'),
            'log_queries' => env('ENHANCED_DB_SERVICE_LOG_QUERIES', true),
            'log_errors' => env('ENHANCED_DB_SERVICE_LOG_ERRORS', true),
            'log_execution_time' => env('ENHANCED_DB_SERVICE_LOG_EXECUTION_TIME', true),
        ],

        // Connection pool configuration
        'connection_pool' => [
            'max_connections' => env('ENHANCED_DB_SERVICE_MAX_CONNECTIONS', 10),
            'pool_timeout' => env('ENHANCED_DB_SERVICE_POOL_TIMEOUT', 30),
            'idle_timeout' => env('ENHANCED_DB_SERVICE_IDLE_TIMEOUT', 300),
            'retry_attempts' => env('ENHANCED_DB_SERVICE_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('ENHANCED_DB_SERVICE_RETRY_DELAY', 100), // milliseconds
        ],

        // Cache configuration
        'cache' => [
            'procedure_exists_ttl' => env('ENHANCED_DB_SERVICE_CACHE_TTL', 3600),
            'enabled' => env('ENHANCED_DB_SERVICE_CACHE_ENABLED', true),
        ],

        // Performance monitoring
        'performance' => [
            'slow_query_threshold' => env('ENHANCED_DB_SERVICE_SLOW_QUERY_THRESHOLD', 2.0),
            'enable_query_profiling' => env('ENHANCED_DB_SERVICE_ENABLE_PROFILING', true),
        ],
    ],
];
