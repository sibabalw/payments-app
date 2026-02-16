<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Lock Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default lock driver that will be used.
    | Supported: "database", "redis"
    |
    */

    'driver' => env('LOCK_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Lock Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the lock drivers for your application.
    | Database driver uses advisory locks or row locks.
    | Redis driver uses Redis for distributed locking.
    |
    */

    'drivers' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'sqlite'),
        ],

        'redis' => [
            'connection' => env('REDIS_LOCK_CONNECTION', 'default'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Lock Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds to wait for a lock to be acquired.
    |
    */

    'timeout' => env('LOCK_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Default Lock Expiration
    |--------------------------------------------------------------------------
    |
    | The number of seconds a lock will be held before expiring.
    |
    */

    'expiration' => env('LOCK_EXPIRATION', 300),
];
