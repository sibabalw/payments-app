<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Idempotency Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default idempotency driver that will be used.
    | Supported: "database", "redis"
    |
    */

    'driver' => env('IDEMPOTENCY_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Idempotency Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the idempotency drivers for your application.
    | Database driver uses database table with unique constraints.
    | Redis driver uses Redis for fast idempotency checks.
    |
    */

    'drivers' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'sqlite'),
            'table' => 'idempotency_keys',
        ],

        'redis' => [
            'connection' => env('REDIS_IDEMPOTENCY_CONNECTION', 'default'),
            'prefix' => 'idempotency:',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default TTL
    |--------------------------------------------------------------------------
    |
    | The default time to live for idempotency keys in seconds.
    | Increased to 7 days for financial operations (bank-grade retention).
    |
    */

    'ttl' => env('IDEMPOTENCY_TTL', 604800), // 7 days (increased from 24 hours)

    /*
    |--------------------------------------------------------------------------
    | Deduplication Window
    |--------------------------------------------------------------------------
    |
    | Time window in seconds for content-based request deduplication.
    |
    */

    'deduplication_window_seconds' => env('IDEMPOTENCY_DEDUPLICATION_WINDOW', 3600), // 1 hour
];
