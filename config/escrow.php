<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Escrow Account Configuration
    |--------------------------------------------------------------------------
    |
    | The platform's single escrow account number. This account is owned
    | by the platform and all businesses deposit into this account.
    |
    */

    'account_number' => env('ESCROW_ACCOUNT_NUMBER', ''),

    /*
    |--------------------------------------------------------------------------
    | Deposit Fee Rate
    |--------------------------------------------------------------------------
    |
    | The fee percentage charged per deposit (not per transaction).
    | Default is 1.5% (0.015).
    |
    */

    'deposit_fee_rate' => env('ESCROW_DEPOSIT_FEE_RATE', 0.015),
];
