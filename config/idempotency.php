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
    |
    */

    'ttl' => env('IDEMPOTENCY_TTL', 86400), // 24 hours
];
