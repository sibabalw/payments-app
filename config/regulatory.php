<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Regulatory Compliance Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for regulatory compliance features including retention
    | policies, audit exports, and WORM storage.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Retention Policy
    |--------------------------------------------------------------------------
    |
    | How long to retain financial data (in years).
    | After this period, data should be archived to cold storage.
    |
    */

    'retention_years' => env('REGULATORY_RETENTION_YEARS', 7),

    /*
    |--------------------------------------------------------------------------
    | WORM Storage (Write Once Read Many)
    |--------------------------------------------------------------------------
    |
    | Enable immutable storage for compliance. When enabled, ledger entries
    | are written to S3 with Object Lock for immutability.
    |
    */

    'worm_storage_enabled' => env('REGULATORY_WORM_STORAGE_ENABLED', false),

    'worm_storage_bucket' => env('REGULATORY_WORM_STORAGE_BUCKET'),

    'worm_storage_region' => env('REGULATORY_WORM_STORAGE_REGION', 'us-east-1'),

    /*
    |--------------------------------------------------------------------------
    | Audit Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for exportable audit packs.
    |
    */

    'export_format' => env('REGULATORY_EXPORT_FORMAT', 'csv'), // csv, json

    'auto_fix_rounding_threshold' => env('REGULATORY_AUTO_FIX_ROUNDING_THRESHOLD', 0.01), // Max amount for auto-fix
];
