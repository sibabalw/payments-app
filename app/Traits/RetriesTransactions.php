<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait RetriesTransactions
{
    /**
     * Execute a transaction with retry logic for lock timeouts
     * Uses exponential backoff with jitter to prevent thundering herd
     *
     * @param  callable  $callback  The transaction callback
     * @param  int  $maxAttempts  Maximum number of retry attempts
     * @param  int  $initialDelay  Initial delay in seconds
     * @param  int  $maxDelay  Maximum delay cap in seconds
     *
     * @throws \Exception
     */
    protected function retryTransaction(callable $callback, int $maxAttempts = 3, int $initialDelay = 1, int $maxDelay = 30): mixed
    {
        $attempt = 0;
        $baseDelay = $initialDelay;

        while ($attempt < $maxAttempts) {
            try {
                return DB::transaction($callback);
            } catch (\Exception $e) {
                $attempt++;

                // Check if it's a lock timeout or deadlock error
                $isRetryableError = $this->isRetryableError($e);

                if (! $isRetryableError || $attempt >= $maxAttempts) {
                    // Not a retryable error or max attempts reached, re-throw
                    if ($attempt >= $maxAttempts && $isRetryableError) {
                        Log::error('Transaction failed after maximum retry attempts', [
                            'attempts' => $attempt,
                            'max_attempts' => $maxAttempts,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    throw $e;
                }

                // Calculate exponential backoff: baseDelay * 2^(attempt-1)
                $exponentialDelay = $baseDelay * (2 ** ($attempt - 1));

                // Add jitter: random value between 0 and 25% of delay
                // This prevents thundering herd when multiple processes retry simultaneously
                $jitter = random_int(0, (int) ($exponentialDelay * 0.25));
                $delay = min($exponentialDelay + $jitter, $maxDelay);

                // Log retry attempt
                Log::warning('Transaction failed due to lock timeout/deadlock, retrying', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'delay' => $delay,
                    'base_delay' => $exponentialDelay,
                    'jitter' => $jitter,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ]);

                // Wait with exponential backoff + jitter before retrying
                // Use usleep for sub-second precision when delay is small
                if ($delay < 1) {
                    usleep((int) ($delay * 1000000));
                } else {
                    sleep((int) $delay);
                    // Add fractional second if needed
                    $fractional = $delay - (int) $delay;
                    if ($fractional > 0) {
                        usleep((int) ($fractional * 1000000));
                    }
                }
            }
        }

        throw new \Exception('Transaction failed after '.$maxAttempts.' attempts');
    }

    /**
     * Check if an exception is a retryable error (lock timeout, deadlock, etc.)
     */
    protected function isRetryableError(\Exception $e): bool
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        // MySQL lock timeout errors (1205 = Lock wait timeout exceeded)
        if ($code === 1205 || str_contains($message, 'Lock wait timeout exceeded')) {
            return true;
        }

        // MySQL deadlock errors (1213 = Deadlock found when trying to get lock)
        if ($code === 1213 || str_contains($message, 'Deadlock found')) {
            return true;
        }

        // PostgreSQL lock timeout errors
        if (str_contains($message, 'lock not available') || str_contains($message, 'deadlock detected')) {
            return true;
        }

        // PostgreSQL serialization failures (40001)
        if ($code === 40001 || str_contains($message, 'serialization failure')) {
            return true;
        }

        // General database lock errors
        if (str_contains($message, 'timeout') && str_contains($message, 'lock')) {
            return true;
        }

        // Connection errors that might be transient
        if (str_contains($message, 'Connection') && (
            str_contains($message, 'lost') || str_contains($message, 'reset')
        )) {
            return true;
        }

        return false;
    }
}
