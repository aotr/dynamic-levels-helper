<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Settings Database
    |--------------------------------------------------------------------------
    |
    | Database to use for settings storage.
    | Options: 'mysql' (default), 'sqlite', 'pgsql', 'sqlsrv'
    |
    | SQLite is auto-created in storage/app/settings.sqlite if:
    | - SETTINGS_DATABASE=sqlite
    | - SETTINGS_SQLITE_AUTO_CREATE=true
    |
    */
    'database' => env('SETTINGS_DATABASE', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | SQLite Auto-Creation
    |--------------------------------------------------------------------------
    |
    | If using SQLite, automatically create DB file + tables if missing.
    |
    */
    'sqlite_auto_create' => env('SETTINGS_SQLITE_AUTO_CREATE', true),
    'sqlite_path' => env('SETTINGS_SQLITE_PATH', 'app/settings.sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('SETTINGS_CACHE_ENABLED', true),
        'ttl' => env('SETTINGS_CACHE_TTL', 604800), // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Logging (Optional)
    |--------------------------------------------------------------------------
    */
    'audit_log' => env('SETTINGS_AUDIT_LOG', false),

    /*
    |--------------------------------------------------------------------------
    | Settings Route Path
    |--------------------------------------------------------------------------
    |
    | URL path where the settings form will be accessible.
    | Configure this to match your application's route definition.
    | Default: /admin/settings
    |
    */
    'route_path' => env('SETTINGS_ROUTE_PATH', '/admin/settings'),

    /*
    |--------------------------------------------------------------------------
    | Encrypted Fields (Optional)
    |--------------------------------------------------------------------------
    |
    | Settings keys to encrypt at rest (e.g., API keys, passwords)
    |
    */
    'encrypted_keys' => [
        // 'api.stripe.secret_key',
        // 'email.password',
    ],
];
