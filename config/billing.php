<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Billing Gateway
    |--------------------------------------------------------------------------
    |
    | The default billing gateway to use for processing subscription charges.
    | Supported: 'mock'
    |
    */

    'gateway' => env('BILLING_GATEWAY', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Mock Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the mock billing gateway used in development.
    |
    */

    'mock' => [
        'success_rate' => env('BILLING_MOCK_SUCCESS_RATE', 0.95),
    ],
];
