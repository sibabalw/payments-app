<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Structured Logging Context Helper
 *
 * Provides consistent structured logging with correlation IDs, business IDs,
 * job IDs, and operation types for better observability and traceability.
 * Supports operation chains for tracking related operations across services.
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

        // Add operation_id if provided in additional context
        if (isset($additional['operation_id'])) {
            $context['operation_id'] = $additional['operation_id'];
        }

        // Add parent_operation_id if provided (for operation chains)
        if (isset($additional['parent_operation_id'])) {
            $context['parent_operation_id'] = $additional['parent_operation_id'];
        }

        return array_merge($context, $additional);
    }

    /**
     * Generate a new operation ID for tracking operation chains
     *
     * @return string Unique operation ID
     */
    public static function generateOperationId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Create a log context with operation chain support
     *
     * @param  string|null  $parentOperationId  Parent operation ID (for chaining)
     * @param  string|null  $correlationId  Correlation ID for tracing
     * @param  int|null  $businessId  Business ID
     * @param  int|null  $jobId  Job ID
     * @param  string|null  $operationType  Operation type
     * @param  int|null  $userId  User ID
     * @param  array  $additional  Additional context data
     * @return array Log context array with operation_id
     */
    public static function createWithOperation(
        ?string $parentOperationId = null,
        ?string $correlationId = null,
        ?int $businessId = null,
        ?int $jobId = null,
        ?string $operationType = null,
        ?int $userId = null,
        array $additional = []
    ): array {
        $operationId = self::generateOperationId();

        return self::create(
            $correlationId,
            $businessId,
            $jobId,
            $operationType,
            $userId,
            array_merge($additional, [
                'operation_id' => $operationId,
                'parent_operation_id' => $parentOperationId,
            ])
        );
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

    /**
     * Log operation start with timing
     *
     * @param  string  $operationName  Name of the operation
     * @param  array  $context  Context array
     * @return array Context with start_time for tracking duration
     */
    public static function logOperationStart(string $operationName, array $context = []): array
    {
        $startTime = microtime(true);
        $context = array_merge($context, [
            'operation_name' => $operationName,
            'start_time' => $startTime,
            'start_time_iso' => now()->toIso8601String(),
        ]);

        self::info("Operation started: {$operationName}", $context);

        return $context;
    }

    /**
     * Log operation end with timing
     *
     * @param  string  $operationName  Name of the operation
     * @param  array  $context  Context array (should include start_time from logOperationStart)
     * @param  bool  $success  Whether operation succeeded
     * @param  array  $result  Optional result data
     */
    public static function logOperationEnd(string $operationName, array $context = [], bool $success = true, array $result = []): void
    {
        $endTime = microtime(true);
        $startTime = $context['start_time'] ?? $endTime;
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $context = array_merge($context, [
            'operation_name' => $operationName,
            'end_time' => $endTime,
            'end_time_iso' => now()->toIso8601String(),
            'duration_ms' => round($duration, 2),
            'success' => $success,
        ]);

        if (! empty($result)) {
            $context['result'] = $result;
        }

        $level = $success ? 'info' : 'error';
        $message = $success
            ? "Operation completed: {$operationName} (took {$duration}ms)"
            : "Operation failed: {$operationName} (took {$duration}ms)";

        self::log($level, $message, $context);
    }

    /**
     * Create operation chain context (for tracking related operations)
     *
     * @param  string  $chainType  Type of operation chain (e.g., 'schedule_execution', 'batch_processing')
     * @param  string|null  $parentOperationId  Parent operation ID
     * @param  array  $additional  Additional context
     * @return array Context with chain information
     */
    public static function createOperationChain(string $chainType, ?string $parentOperationId = null, array $additional = []): array
    {
        $operationId = self::generateOperationId();

        return array_merge($additional, [
            'operation_id' => $operationId,
            'parent_operation_id' => $parentOperationId,
            'chain_type' => $chainType,
            'chain_level' => $parentOperationId ? 1 : 0, // Simple level tracking
        ]);
    }
}
