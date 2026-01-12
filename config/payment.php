<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | The default payment gateway to use for processing payments.
    | Supported: 'mock'
    |
    */

    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Mock Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the mock payment gateway used in development.
    |
    */

    'mock' => [
        'success_rate' => env('PAYMENT_MOCK_SUCCESS_RATE', 0.95),
    ],
];
