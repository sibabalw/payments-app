<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

/**
 * Structured Logging Context Helper
 *
 * Provides consistent structured logging with correlation IDs, business IDs,
 * job IDs, and operation types for better observability and traceability.
 */
class LogContext
{
    /**
     * Create a log context array with standard fields
     *
     * @param  string|null  $correlationId  Correlation ID for tracing
     * @param  int|null  $businessId  Business ID
     * @param  int|null  $jobId  Job ID (payment_job_id or payroll_job_id)
     * @param  string|null  $operationType  Operation type (e.g., 'payment_process', 'payroll_process', 'settlement')
     * @param  int|null  $userId  User ID
     * @param  array  $additional  Additional context data
     * @return array Log context array
     */
    public static function create(
        ?string $correlationId = null,
        ?int $businessId = null,
        ?int $jobId = null,
        ?string $operationType = null,
        ?int $userId = null,
        array $additional = []
    ): array {
        $context = [];

        if ($correlationId) {
            $context['correlation_id'] = $correlationId;
        }

        if ($businessId) {
            $context['business_id'] = $businessId;
        }

        if ($jobId) {
            $context['job_id'] = $jobId;
        }

        if ($operationType) {
            $context['operation_type'] = $operationType;
        }

        if ($userId) {
            $context['user_id'] = $userId;
        }

        return array_merge($context, $additional);
    }

    /**
     * Log with structured context
     *
     * @param  string  $level  Log level (info, warning, error, debug)
     * @param  string  $message  Log message
     * @param  array  $context  Context array (from create() or custom)
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $method = match ($level) {
            'debug' => 'debug',
            'info' => 'info',
            'warning' => 'warning',
            'error' => 'error',
            default => 'info',
        };

        Log::{$method}($message, $context);
    }

    /**
     * Log info with structured context
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    /**
     * Log warning with structured context
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    /**
     * Log error with structured context
     */
    public static function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    /**
     * Log debug with structured context
     */
    public static function debug(string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }
}
