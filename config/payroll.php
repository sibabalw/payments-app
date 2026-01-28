<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payroll Transaction Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for retrying transactions that fail due to lock timeouts.
    | These settings help handle high concurrency scenarios.
    |
    */

    'transaction_max_retries' => env('PAYROLL_TRANSACTION_MAX_RETRIES', 3),

    'transaction_retry_delay' => env('PAYROLL_TRANSACTION_RETRY_DELAY', 1),

    'transaction_max_delay' => env('PAYROLL_TRANSACTION_MAX_DELAY', 30),

    'transaction_timeout' => env('PAYROLL_TRANSACTION_TIMEOUT', 60), // seconds

    /*
    |--------------------------------------------------------------------------
    | Payroll Processing Configuration
    |--------------------------------------------------------------------------
    |
    | General configuration for payroll processing operations.
    |
    */

    'batch_size' => env('PAYROLL_BATCH_SIZE', 100),

    'lock_timeout' => env('PAYROLL_LOCK_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Payroll Reservation Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cleaning up stale escrow reservations from failed jobs.
    |
    */

    'reservation_cleanup_timeout' => env('PAYROLL_RESERVATION_CLEANUP_TIMEOUT', 3600), // Default 1 hour

    /*
    |--------------------------------------------------------------------------
    | Calculation Version
    |--------------------------------------------------------------------------
    |
    | Version number for calculation logic. Increment this when calculation
    | logic changes to ensure proper validation and recalculation.
    |
    */

    'calculation_version' => env('PAYROLL_CALCULATION_VERSION', 1),

    /*
     * |--------------------------------------------------------------------------
     * | Stuck Job Detection Configuration
     * |--------------------------------------------------------------------------
     * |
     * | Configuration for detecting and recovering stuck payroll jobs.
     * |
     */

    'stuck_job_timeout_hours' => env('PAYROLL_STUCK_JOB_TIMEOUT_HOURS', 2),

    /*
     * |--------------------------------------------------------------------------
     * | Schedule Lock Configuration
     * |--------------------------------------------------------------------------
     * |
     * | Configuration for distributed locks when processing schedules.
     * |
     */

    'schedule_lock_ttl' => env('PAYROLL_SCHEDULE_LOCK_TTL', 600), // 10 minutes

    'schedule_lock_heartbeat_interval' => env('PAYROLL_SCHEDULE_LOCK_HEARTBEAT_INTERVAL', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Batch Processing Configuration
    |--------------------------------------------------------------------------
    */

    'batch_size' => env('PAYROLL_BATCH_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Settlement Window Configuration
    |--------------------------------------------------------------------------
    */

    'settlement_window_type' => env('PAYROLL_SETTLEMENT_WINDOW_TYPE', 'hourly'), // hourly, daily, custom

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    */

    'circuit_breaker_failure_threshold' => env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
    'circuit_breaker_timeout' => env('CIRCUIT_BREAKER_TIMEOUT', 60),
    'circuit_breaker_half_open_success_threshold' => env('CIRCUIT_BREAKER_HALF_OPEN_SUCCESS_THRESHOLD', 3),

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Configuration
    |--------------------------------------------------------------------------
    */

    'reconciliation_auto_fix_max' => env('PAYROLL_RECONCILIATION_AUTO_FIX_MAX', 10.00), // Maximum amount to auto-fix
    'reconciliation_interval_minutes' => env('PAYROLL_RECONCILIATION_INTERVAL_MINUTES', 60), // How often to run reconciliation

    /*
    |--------------------------------------------------------------------------
    | Ledger Configuration (Bank-Grade)
    |--------------------------------------------------------------------------
    */

    'ledger_source_of_truth' => true, // Explicit flag: Ledger is source of truth, balances are cached projections

    'currency_minor_unit_divisor' => env('PAYROLL_CURRENCY_MINOR_UNIT_DIVISOR', 100), // 100 for ZAR (cents)

    'snapshot_frequency' => env('PAYROLL_SNAPSHOT_FREQUENCY', 'daily'), // daily, weekly, etc.

    'posting_delay_seconds' => env('PAYROLL_POSTING_DELAY_SECONDS', 0), // How long entries stay PENDING before POSTED (0 = immediate)
];
