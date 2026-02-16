<?php

namespace App\Services;

class ErrorClassificationService
{
    /**
     * Classify error type
     */
    public function classify(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $code = $exception->getCode();

        // Permanent failures - should not retry
        if ($this->isPermanentFailure($exception)) {
            return 'permanent';
        }

        // Transient failures - can retry
        if ($this->isTransientFailure($exception)) {
            return 'transient';
        }

        // Unknown - default to transient for safety
        return 'transient';
    }

    /**
     * Check if error is a permanent failure
     */
    public function isPermanentFailure(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        $code = $exception->getCode();

        $permanentPatterns = [
            'Insufficient escrow balance',
            'Failed to reserve funds',
            'Cannot update immutable',
            'Invalid status transition',
            'Validation failed',
            'Record not found',
        ];

        foreach ($permanentPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        // Database unique/duplicate constraint violations (MySQL and PostgreSQL)
        if ($this->isUniqueConstraintViolation($exception)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the exception is a unique constraint or duplicate key violation.
     * Works for both MySQL and PostgreSQL so duplicate job insert or settlement
     * assignment can be treated as "already done" (idempotent).
     *
     * MySQL: SQLSTATE 23000 (integrity constraint), message "Duplicate entry"
     * PostgreSQL: SQLSTATE 23505 (unique_violation), message "unique constraint" / "duplicate key"
     */
    public function isUniqueConstraintViolation(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        $code = $exception->getCode();

        // MySQL/MariaDB
        if ($code === '23000' || $code === 23000 || str_contains($message, 'Duplicate entry')) {
            return true;
        }

        // PostgreSQL (SQLSTATE 23505 = unique_violation)
        if ($code === '23505' || $code === 23505 || str_contains($message, 'unique constraint') || str_contains($message, 'duplicate key value')) {
            return true;
        }

        return false;
    }

    /**
     * Check if error is a transient failure
     */
    public function isTransientFailure(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        $code = $exception->getCode();

        // Lock timeouts
        if ($code === 1205 || str_contains($message, 'Lock wait timeout')) {
            return true;
        }

        // Deadlocks
        if ($code === 1213 || str_contains($message, 'Deadlock')) {
            return true;
        }

        // Connection errors
        if (str_contains($message, 'Connection') && (
            str_contains($message, 'lost') || str_contains($message, 'reset')
        )) {
            return true;
        }

        // Network errors
        if (str_contains($message, 'timeout') || str_contains($message, 'network')) {
            return true;
        }

        return false;
    }

    /**
     * Get error category for aggregation
     */
    public function getCategory(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'balance') || str_contains($message, 'funds')) {
            return 'balance';
        }

        if (str_contains($message, 'lock') || str_contains($message, 'timeout')) {
            return 'concurrency';
        }

        if (str_contains($message, 'validation') || str_contains($message, 'invalid')) {
            return 'validation';
        }

        if (str_contains($message, 'network') || str_contains($message, 'connection')) {
            return 'network';
        }

        return 'other';
    }
}
