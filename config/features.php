<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Redis Feature Flags
    |--------------------------------------------------------------------------
    |
    | These flags control whether Redis is used for various features.
    | When disabled, the database is used as the default backend.
    | Redis is a drop-in accelerator, not a dependency.
    |
    */

    'redis' => [
        'enabled' => env('REDIS_ENABLED', false),
        'locks' => env('REDIS_LOCKS_ENABLED', false),
        'idempotency' => env('REDIS_IDEMPOTENCY_ENABLED', false),
        'queues' => env('REDIS_QUEUES_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL Feature Flags
    |--------------------------------------------------------------------------
    |
    | These flags control PostgreSQL-specific features.
    |
    */

    'postgres_notifications' => env('POSTGRES_NOTIFICATIONS_ENABLED', true),
];
