<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Exponential Backoff Retry Trait
 *
 * Provides exponential backoff retry logic for transient failures.
 */
trait ExponentialBackoffRetry
{
    /**
     * Execute a callback with exponential backoff retry
     *
     * @param  callable  $callback  Callback to execute
     * @param  int  $maxRetries  Maximum number of retries (default: 3)
     * @param  int  $initialDelayMs  Initial delay in milliseconds (default: 100)
     * @param  float  $backoffMultiplier  Backoff multiplier (default: 2.0)
     * @param  int  $maxDelayMs  Maximum delay in milliseconds (default: 5000)
     * @param  callable|null  $shouldRetry  Optional function to determine if error should be retried
     * @return mixed Result from callback
     *
     * @throws \Exception If all retries are exhausted
     */
    protected function retryWithBackoff(
        callable $callback,
        int $maxRetries = 3,
        int $initialDelayMs = 100,
        float $backoffMultiplier = 2.0,
        int $maxDelayMs = 5000,
        ?callable $shouldRetry = null
    ) {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;

                // Check if we should retry this error
                if ($shouldRetry && ! $shouldRetry($e)) {
                    throw $e;
                }

                // Check if we've exhausted retries
                if ($attempt >= $maxRetries) {
                    break;
                }

                // Calculate delay with exponential backoff
                $delayMs = min(
                    (int) ($initialDelayMs * pow($backoffMultiplier, $attempt)),
                    $maxDelayMs
                );

                // Add jitter to prevent thundering herd
                $jitter = random_int(0, (int) ($delayMs * 0.1));
                $delayMs += $jitter;

                Log::warning('Retrying operation with exponential backoff', [
                    'attempt' => $attempt + 1,
                    'max_retries' => $maxRetries,
                    'delay_ms' => $delayMs,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                ]);

                // Wait before retrying
                usleep($delayMs * 1000); // Convert to microseconds

                $attempt++;
            }
        }

        // All retries exhausted
        Log::error('Operation failed after all retries', [
            'max_retries' => $maxRetries,
            'final_error' => $lastException?->getMessage(),
            'final_error_class' => $lastException ? get_class($lastException) : null,
        ]);

        throw $lastException ?? new \RuntimeException('Operation failed with unknown error');
    }

    /**
     * Check if an exception is a transient failure (should be retried)
     *
     * @param  \Exception  $e  Exception to check
     * @return bool True if should retry
     */
    protected function isTransientFailure(\Exception $e): bool
    {
        // Database connection errors
        if ($e instanceof \Illuminate\Database\QueryException) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            // PostgreSQL connection errors
            if (str_contains($errorMessage, 'connection') || str_contains($errorMessage, 'timeout')) {
                return true;
            }

            // Deadlock errors (40001)
            if ($errorCode === '40001' || str_contains($errorMessage, 'deadlock')) {
                return true;
            }

            // Lock timeout errors
            if (str_contains($errorMessage, 'lock timeout') || str_contains($errorMessage, 'Lock wait timeout')) {
                return true;
            }
        }

        // Network errors
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        // Timeout errors
        if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'timed out')) {
            return true;
        }

        return false;
    }
}
